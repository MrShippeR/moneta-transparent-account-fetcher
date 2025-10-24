<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function kamprnavCleanText($node) {
    $text = $node->textContent ?? '';

    // odstraníme CSS class a inline styly z textu
    $text = preg_replace('/\.css-[a-zA-Z0-9_-]+\{[^}]*\}/', '', $text);
    $text = preg_replace('/\.css-[a-zA-Z0-9_-]+/', '', $text);

    // odstraníme vícenásobné mezery, nové řádky a HTML entity
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = str_replace("\xc2\xa0", ' ', $text); // non-breaking space
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

function kamprnavMonetaBankingParseTable() {
    $url = 'https://transparentniucty.moneta.cz/245355790';
    $html = file_get_contents($url);
    if (!$html) {
        return ['error' => 'Nepodařilo se načíst stránku s transparentním účtem.'];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // tabulka s platbami
    $table = $xpath->query("//table[contains(@class, 'eywmqk60')]")->item(0);
    if (!$table) {
        return ['error' => 'Tabulka plateb nebyla nalezena.'];
    }

    // načti hlavičky
    $headers = [];
    foreach ($xpath->query(".//thead//th", $table) as $th) {
        $headers[] = kamprnavCleanText($th);
    }

    $data = [];
    foreach ($xpath->query(".//tbody/tr", $table) as $row) {
        $cells = $xpath->query(".//td", $row);
        if ($cells->length === 0) continue;

        $rowData = [
            0 => '', // Datum
            1 => '', // Název účtu
            2 => '', // Datum znovu
            3 => '', // Variabilní symbol
            4 => ''  // Částka
        ];

        foreach ($cells as $i => $cell) {
            $key = $headers[$i] ?? "Sloupec_$i";

            if ($key === "Datum") {
                $rowData[0] = kamprnavCleanText($cell);
                $rowData[2] = $rowData[0]; // datum znovu
            } elseif ($key === "Název účtu/Poznámka") {
                $spans = $xpath->query(".//span", $cell);
                $ucet = $spans->item(0) ? kamprnavCleanText($spans->item(0)) : "";
                $rowData[1] = $ucet;
            } elseif ($key === "Variabilní symbol") {
                $rowData[3] = kamprnavCleanText($cell);
            } elseif ($key === "Částka") {
                $rowData[4] = kamprnavCleanText($cell);
            }
        }

        // přidat řádek jen pokud má datum a částku
        if (!empty($rowData[0]) && !empty($rowData[4])) {
            $data[] = $rowData;
        }
    }
    return $data;
    // Array ( [14] => Array ( [0] => 16. 10. 2025 [1] => VACH MAREK [2] => 16. 10. 2025 [3] => 11854174 [4] => 1 500,00 Kč ))
}


function kamprnavGetStringBetween($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

// https://stackoverflow.com/questions/35367907/file-get-contents-returns-unreadable-text-for-a-specific-url
if( !function_exists('gzdecode') ){
    function gzdecode( $data ){ 
        $g=tempnam('/tmp','ff'); 
        @file_put_contents( $g, $data );
        ob_start();
        readgzfile($g);
        $d=ob_get_clean();
        unlink($g);
        return $d;
    }   
}























