<?php

class CSV_Importer {

    public function __construct(string $filePath) {
        $this->blacklist = [
            'sumup',
            'paypal'
        ];
        $this->file = $filePath;
        $this->lines = [];
        $this->errors = [['type' => 'errors']];
        $this->successes = [['type' => 'successes']];
        $this->failed = [['type' => 'failes']];
        $this->columns = [
            3 => 'payer_name',
            4 => 'purpose',
            5 => 'iban',
            16 => 'have'
        ];
        $this->picked = [];
        
    }

    public function init() {
        $this->setup();
        $this->handler();
    }

    public function setup() {
        $this->read_csv();
        $this->pick_lines();
        $this->cherry_pick_cells();
    }

    public function handler() {

        require_once(BBB_BAC_IMPORTER_PATH . 'includes/class-file-handler.php');

        $controlFileData = [];
        for ($i = 0; $i < count($this->cherry_picked_lines); $i++) {
            $line = $this->cherry_picked_lines[$i];
            // list($booking_day, $) = $line;
            $this->evaluate($line);
        }
        $controlFileData[] = $this->successes;
        $controlFileData[] = $this->failed;
        $controlFileData[] = $this->errors;
        
        $fileHandler = new BBB_File_Handler();
        $fileHandler->createCSV($controlFileData);
        $fileHandler->download();
    }
    
    public function evaluate($line) 
    {
        $order_id = $this->get_order_id($line);
        if (!isset($order_id) || empty($order_id)) {
            $order_id = $this->get_order_id_by_name($line);
        }

        if (!isset($order_id) || empty($order_id)) {
            $this->errors[] = $line;
            return;
        }

        $line['order_id'] = $order_id;
        $order_price = $this->get_order_price($line);
        $line['order_price'] = $order_price;

        $line['order_status'] = $this->get_order_status($line);
        
        $payer_name = $line['payer_name'];
        $valid = $this->validate_bac($line);
        if (!$valid) {
            $this->errors[] = $line;
            return false;
        }
    
        $success = $this->update_order_status($line);
        if ($success) {
            $this->successes[] = $line;
            return $success;
        } else {
            $this->failed[] = $line;
        }
    }

    public function read_csv() {
        $file_handle = fopen($this->file, 'r');
        while (!feof($file_handle)) {
            $this->lines[] = fgetcsv($file_handle, 1024, ';');
        }
        fclose($file_handle);
        return $this->lines;
    }

    public function pick_lines() {
        $array = [];
        for ($i = 0; $i < count($this->lines); $i++) {
            // if header
            if ($i === 0) continue;
            $line = $this->lines[$i];
            if (count($line) === 18 ) {
                $array[] = $line;
            }
        }
        $this->picked = $array;
    }

    public function cherry_pick_cells() {
        $outerArray = [];

        foreach($this->picked AS $line) {
            $innerArray = [];
            foreach($line AS $key => $cell) {
                $pick_keys = array_keys($this->columns);
                if (in_array($key, $pick_keys)) {
                    switch($key) {
                        case 16:
                            $english_format = str_replace(',', '.', $cell);
                            $float = floatval($english_format);
                            $formated = number_format($float, 2, '.', ',');
                            $innerArray[$this->columns[$key]] = $formated;
                            break;
                        default:
                            $innerArray[$this->columns[$key]] = $cell;
                    }
                }
            }
            $outerArray[] = $innerArray;
        }
        $this->cherry_picked_lines = $outerArray;
    }

    public function validate_bac($line) {
        try {
            $valid = false;

            $order_price = floatval($line['order_price']);
            $have = floatval($line['have']);

            $blacklisted = $this->check_blacklist($line);
            if ($blacklisted) {
                return $valid;
            }

            if (isset($line['order_id']) && !empty($line['order_id']) && $line['order_price'] !== NULL) {
                if ($have >= $order_price) {
                    return true;
                }
            }
            return $valid;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function get_order_id( $line) {

        $pattern = '/\d+/';
        $text = $line['purpose'];

        preg_match($pattern, $text, $matches);

        return $matches[0];
    }

    public function get_order_price($line) {
        try {
            $order_id = (int) $line['order_id'];
            $order = new WC_Order($order_id);

            $order_price = $order->get_total();
            return $order_price;
        } catch (Exception $e)  {
            error_log($e->getMessage());
        }
    }

    public function get_name($line) {
        $pattern = '/\b+/';
        $text = $line['payer_name'];

        preg_match($pattern, $text, $matches);

        if ($matches) {
            return $matches[0];
        }
        return;
    }

    public function get_order_id_by_name($line) 
    {
        global $wpdb;
        try {

            $payer_name = $line['payer_name'];
            $payer_name_array = explode(' ', $payer_name);
            list($first_name, $last_name) = $payer_name_array;
            
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}postmeta WHERE ((meta_key = '_billing_first_name' AND meta_value = %s) OR (meta_key = '_billing_last_name' AND meta_value = %s)) OR ((meta_key = '_billing_first_name' AND meta_value = %s) OR (meta_key = '_billing_last_name' AND meta_value = %s)) ORDER BY post_id", $first_name, $last_name, $last_name, $first_name), ARRAY_A);
            
            $post_ids = array_column($results, 'post_id');
            $uniques = array_unique($post_ids);
            $duplicates = array_diff_assoc($post_ids, $uniques);
            $order_id = array_values($duplicates)[0];
            
            return $order_id;
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function get_order_status($line)
    {
        try {
            $order_id = (int) $line['order_id'];
            $order = new WC_Order($order_id);
            $order_status = $order->get_status();

            return $order_status;
        } catch(Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * paypal, sumup etc
     * 
     * returns true if contains blacklisted name
     */

    public function check_blacklist($line, $blacklist = []) 
    {
        $blacklist = (empty($blacklist)) ? $this->blacklist : $blacklist;
        $payer_name = strtolower($line['payer_name']);
        if (in_array($payer_name, $blacklist)) {
            return true;
        }
        return false;
    }

    public function update_order_status($line) 
    {
        try {
            $order_id = $line['order_id'];
            $order_status = $line['order_status'];
            if ($order_status !== 'on-hold') {
                return false;
            } 
            $order = new WC_Order($order_id);
            $updated = $order->update_status('completed', 'Update Status via Überweisungsimport');
            return $updated;
        } catch(Exception $e) {
            error_log($e->getMessage());
        }
    }

}