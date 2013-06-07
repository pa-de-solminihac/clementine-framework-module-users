<?php
$user = array();
if (isset($data['user'])) {
    $user = $data['user'];
}
?>
        <input type="hidden" name="id" value="<?php
    if ($request->get('int', 'id')) {
        echo $request->get('int', 'id');
    } else {
        echo '0';
    }
?>" />
        <input type="hidden" name="mdpOK" value="<?php
if (!empty($user['password'])) {
    echo $user['password'];
} else {
    echo '0';
}
?>" />
        <table class="clementine_users_edit" id="clementine_users_edit">
            <thead>
                <tr>
                    <th class="col_login">Adresse e-mail </th>
                    <th class="col_pass">Mot de passe</th>
                    <th class="col_confpass">Confirmation du mot de passe</th>
                    <th class="col_submit"></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col_login form_users_edit_mail">
                        <input type="text" name="login" size="20" value="<?php echo (isset($user['login'])) ? $user['login'] : ''; ?>" />
                    </td>
                    <td class="col_pass form_users_edit_mdp">
                        <input type="password" name="password" size="20" value="<?php echo (isset($user['password'])) ? $user['password'] : ''; ?>" />
                    </td>
                    <td class="col_confpass form_users_edit_mdp_confirm">
                        <input type="password" name="password_conf" size="20" value="<?php echo (isset($user['password'])) ? $user['password'] : ''; ?>" />
                    </td>
                    <td class="col_submit form_users_edit_submit">
                        <input type="submit" name="valider" value="valider" />
                    </td>
                </tr>
            </tbody>
        </table>
