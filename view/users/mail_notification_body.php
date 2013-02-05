Bonjour,<br />
<br />
Un nouvel utilisateur s'est inscrit sur votre site.<br />
<br />
<strong>Adresse e-mail</strong><br />
<?php
echo $this->getModel('fonctions')->htmlentities($data['user']['login']);
?><br />
<br />
<strong>Groupe(s)</strong><br />
<?php
$groups = array_keys($this->getModel('users')->getGroupsByUser($data['user']['id']));
if ($groups) {
    echo $this->getModel('fonctions')->htmlentities(implode(', ', $groups));
} else {
    echo '<em>aucun</em>';
}
?><br />
<br />
Vivement le prochain !<br />
<hr />
<em>Note : ceci est un message automatique de notification de création de compte utilisateur. Merci de ne pas y répondre directement.</em>
