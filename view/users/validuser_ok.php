<?php
$id = $request->get('int', 'id');
$isnew = $request->get('int', 'isnew');
if ($isnew) {
?>
    Votre inscription s'est bien déroulée
<?php
} else {
    $this->getModel('fonctions')->redirect(__WWW__);
}
?>
