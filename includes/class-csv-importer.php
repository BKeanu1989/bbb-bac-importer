<?php

class CSV_Importer {

    public function __construct(string $filePath) {
        $this->file = $filePath;
        $this->lines = [];
        $this->errors = [];
        $this->columns = [
            3 => 'payer_name',
            4 => 'purpose',
            5 => 'iban',
            15 => 'have'
        ];
        $this->picked = [];
    }

    public function init() {
        $this->setup();
        $this->read_csv();
        $this->handler();
    }

    public function setup() {
        $this->read_csv();
        $this->pick_lines();
        $this->cherry_pick_cells();
    }

    public function handler() {
        for ($i = 0; $i < count($this->lines); $i++) {
            $line = $this->lines[$i];
            // list($booking_day, $) = $line;
            $this->cherry_pick_cells($line);

            $order_id = $this->get_order_id($this->picked);
            $valid = $this->validate_bac($this->picked);
            if (!$valid) {
                $this->errors[] = $line;
            }

            $success = $this->update_order_status($this->picked);
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
                    $innerArray[$this->columns[$key]] = $cell;
                }
            }
            $outerArray[] = $innerArray;
        }
        $this->picked_v2 = $outerArray;
    }

    public function validate_bac($line) {
        $valid = false;

        // is an order id given?
        // ||  if not -> check for name
        // is payed sum same or larger than price?

        return $valid;
    }

    public function get_order_id( $line) {

        $pattern = '\d+';
        preg_match($pattern, $text, $matches);

        return $matches[0];
    }

    public function get_name($line) {
        return $name;
    }

    public function update_order_status($line) {

    }

}