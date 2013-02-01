Bonjour, <br />
<br />
Une demande de renouvellement de mot de passe a été reçue pour votre compte sur <a href="<?php echo __WWW__; ?>"><?php echo Clementine::$config['clementine_global']['site_name']; ?></a><br />
<br />
Cette demande ayant été confirmée, votre mot de passe a été renouvelé.<br />
<br />
<strong>Votre nouveau mot de passe est :</strong><br />
__CLEMENTINE_MAIL_ANONYMIZE_START__
<?php
echo $data['newpass'];
?>__CLEMENTINE_MAIL_ANONYMIZE_STOP__<br />
<br />
Bonne journée,<br />
<hr />
<em>Note : cet e-mail a été envoyé automatiquement suite à une demande reçue sur notre site. Merci de ne pas y répondre.</em>
