<?php
$ns = $this->getModel('fonctions');
if (!isset($data['alldata']['simulate_url'])) {
    $data['alldata']['simulate_url'] = __WWW__ . '/users/simulate';
}
if (!isset($data['alldata']['edit_url'])) {
    $data['alldata']['edit_url'] = __WWW__ . '/users/edit';
}
if (!(isset($data['alldata']['hidden_sections']['readbutton']) && ($data['alldata']['hidden_sections']['readbutton']))) {
?>
                    <a class="clementine_crud-list-readbutton readbutton" href="<?php echo __WWW__ . '/' . $data['alldata']['class'] . '/read?' . $ns->htmlentities($data['current_key']); ?>">Afficher</a>
<?php
}
if (!(isset($data['alldata']['hidden_sections']['updatebutton']) && ($data['alldata']['hidden_sections']['updatebutton']))) {
?>
                    <a class="clementine_crud-list-updatebutton updatebutton" href="<?php echo __WWW__ . '/' . $data['alldata']['class'] . '/update?' . $ns->htmlentities($data['current_key']); ?>">Modifier</a>
<?php
}
// masqué par défaut
if (!(isset($data['alldata']['hidden_sections']['duplicatebutton']) && ($data['alldata']['hidden_sections']['duplicatebutton']))) {
?>
                    <a class="clementine_crud-list-duplicatebutton duplicatebutton" href="<?php echo __WWW__ . '/' . $data['alldata']['class'] . '/create?duplicate=1&' . $ns->htmlentities($data['current_key']); ?>">Dupliquer</a>
<?php
}
if (!(isset($data['alldata']['hidden_sections']['delbutton']) && ($data['alldata']['hidden_sections']['delbutton']))) {
?>
                    <a class="clementine_crud-list-delbutton delbutton" href="<?php echo __WWW__ . '/' . $data['alldata']['class'] . '/delete?' . $ns->htmlentities($data['current_key']); ?>">Supprimer</a>
<?php
}
?>
                <a class="edit_user" title="modifier" href="<?php echo $ns->mod_param($data['alldata']['edit_url'], 'id', $data['ligne']['clementine_users.id']); ?>" >
                    <img src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/edit.png" />
                </a>
                <a class="simulate_user" title="simuler" href="<?php echo $ns->mod_param($data['alldata']['simulate_url'], 'id', $data['ligne']['clementine_users.id']); ?>" >
                    <img alt="simuler" src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/simulate.png" />
                </a>
