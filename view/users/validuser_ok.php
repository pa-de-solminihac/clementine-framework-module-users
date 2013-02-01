<?php
$ns = $this->getModel('fonctions');
$id = $ns->ifGet('int', 'id');
$isnew = $ns->ifGet('int', 'isnew');
if ($isnew) {
?>
    Votre inscription s'est bien déroulée
<?php
} else {
    $this->getModel('fonctions')->redirect(__WWW__);
}
?>
