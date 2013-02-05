<div class="form_users_create">
    <form method="post" action="<?php echo __WWW__; ?>/users/validuser" enctype="multipart/form-data">
        <input type="hidden" name="id" value="" />
        <input type="hidden" name="mdpOK" value="0" />

        <table class="clementine_users_create" id="clementine_users_create">
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
                    <td class="col1 form_users_create_mail">
                        <input type="text" name="login" size="20" value="" />
                    </td>
                    <td class="col2 form_users_create_mdp">
                        <input type="password" name="password" size="20" value="" />
                    </td>
                    <td class="col3 form_users_create_mdp_confirm">
                        <input type="password" name="password_conf" size="20" value="" />
                    </td>
                    <td class="col4 form_users_create_submit">
                        <input type="submit" name="valider" value="valider" />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</div>
