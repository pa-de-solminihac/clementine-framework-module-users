<?php
$ns = $this->getModel('fonctions');
$user = array();
if (isset($data['user'])) {
    $user = $data['user'];
}
?>
        <input type="hidden" name="id" value="<?php
    if ($ns->ifGet('int', 'id')) {
        echo $ns->ifGet('int', 'id');
    } else {
        echo '0';
    }
?>" />
        <input type="hidden" name="mdpOK" value="<?php
if ($user['password']) {
    echo $user['password'];
} else {
    echo '0';
}
?>" />
        <table class="clementine_users_edit" id="clementine_users_edit">
            <thead>
                <tr>
                    <th class="col1">Adresse email </th>
                    <th class="col2">Mot de passe</th>
                    <th class="col3">Confirmation du mot de passe</th>
                    <th class="col4"></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col1 form_users_edit_mail">
                        <input type="text" name="login" size="20" value="<?php echo (isset($user['login'])) ? $user['login'] : ''; ?>" />
                    </td>
                    <td class="col2 form_users_edit_mdp">
                        <input type="password" name="password" size="20" value="<?php echo (isset($user['password'])) ? $user['password'] : ''; ?>" />
                    </td>
                    <td class="col3 form_users_edit_mdp_confirm">
                        <input type="password" name="password_conf" size="20" value="<?php echo (isset($user['password'])) ? $user['password'] : ''; ?>" />
                    </td>
                    <td class="col4 form_users_edit_submit">
                        <input type="submit" name="valider" value="valider" />
                    </td>
                </tr>
            </tbody>
        </table>
