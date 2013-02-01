<?php
$ns = $this->getModel('fonctions');
?>
Bonjour,<br />
<br />
Votre inscription sur <a href="<?php echo __WWW__; ?>"><?php echo Clementine::$config['clementine_global']['site_name']; ?></a> s'est bien déroulée.<br />
<br />
Rappel de vos identifiants :<br />
<br />
<strong>Identifiant</strong><br />
<?php
echo $ns->strip_tags($data['user']['login']);
?><br />
<br />
<strong>Mot de passe</strong><br />
__CLEMENTINE_MAIL_ANONYMIZE_START__
<?php
$pass = $data['isnew']['password'];
echo $ns->strip_tags($pass);
?>__CLEMENTINE_MAIL_ANONYMIZE_STOP__<br />
<br />
Conservez ce message précieusement.<br />
<hr />
<em>Note : ceci est un message automatique. Merci de ne pas y répondre directement.</em>
