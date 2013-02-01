<?php
// surcharge des titres de section
/*$data['error_sections']['missing_fields'] = 'Vous devez remplir les champs suivants :' . "\r\n";*/
// surcharge des messages d'erreur
/*if (isset($data['errors']['missing_fields']['mail'])) {*/
    /*$data['errors']['missing_fields']['mail'] = '- adresse e-mail' . "\r\n";*/
/*}*/
if (!isset($data['error_sections'])) {
    $data['error_sections'] = array();
}
if (!isset($data['error_sections']['missing_fields'])) {
    $data['error_sections']['missing_fields'] = 'Vous devez remplir les champs suivants :' . "\r\n";
}
if (isset($data['errors'])) {
    foreach ($data['errors'] as $type => $erreur) {
        if (is_array($erreur) && count($erreur)) {
            if (isset($data['error_sections'][$type]) && strlen($data['error_sections'][$type])) {
                if ($request->AJAX) {
                    echo $data['error_sections'][$type];
                } else {
                    echo nl2br($data['error_sections'][$type]);
                }
            }
            foreach ($erreur as $key => $msg) {
                foreach ((array) $msg as $txt) {
                    if ($request->AJAX) {
                        echo $txt;
                    } else {
                        echo nl2br($txt);
                    }
                }
            }
        } else {
            foreach ((array) $erreur as $txt) {
                if ($request->AJAX) {
                    echo $txt;
                } else {
                    echo nl2br($txt);
                }
            }
        }
    }
}
?>
