<?php
class BBB_File_Handler{

    public function __construct() 
    {
        $this->fileName = date('Y-m-d');
    }

    public function createCSV(array $data, $header = '') 
    {
        if ($header && !empty($header)) {
            array_unshift($data, $header);
        }
        $fp = fopen(BBB_BAC_IMPORTER_UPLOADS . "/{$this->fileName}.csv", 'w');

        // types == success, fails, errors
        foreach($data AS $types) {
            if (is_array($types)) {
                foreach ($types as $fields) {
                    fputcsv($fp, $fields);
                }
            } else {
                fputcsv($fp, $types);
            }
        }
        fclose($fp);
    }

    public function download($type = '.csv') 
    {
        $filepath = BBB_BAC_IMPORTER_UPLOADS . '/' . $this->fileName . $type;
        $baseName = basename($filepath);
        if(file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$baseName.'"');
            // header('Content-Disposition: attachment; filename="'.BBB_BAC_IMPORTER_UPLOADS.basename($filepath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            // header('Content-Length: ' . filesize(BBB_BAC_IMPORTER_UPLOADS . $filepath .'.csv'));
            header('Content-Length: ' . filesize($filepath));
            flush(); // Flush system output buffer
            readfile($filepath);
            exit; 
        }
    }
}