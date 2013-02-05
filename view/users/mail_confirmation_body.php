Bonjour,<br />
<br />
Votre inscription sur <a href="<?php echo __WWW__; ?>"><?php echo Clementine::$config['clementine_global']['site_name']; ?></a> s'est bien déroulée.<br />
<br />
Rappel de vos identifiants :<br />
<br />
<strong>Identifiant</strong><br />
<?php
echo $this->getModel('fonctions')->htmlentities($data['user']['login']);
?><br />
<br />
<strong>Mot de passe</strong><br />
<?php
$pass = $data['isnew']['password'];
echo $this->getModel('fonctions')->htmlentities($pass);
?><br />
<br />
Conservez ce message précieusement.<br />
<hr />
<em>Note : ceci est un message automatique de notification de création de compte utilisateur. Merci de ne pas y répondre directement.</em>
