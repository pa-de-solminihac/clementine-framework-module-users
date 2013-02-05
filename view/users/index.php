<div class="form_users_index">
    <a class="form_users_index_add" title="créer" href="<?php echo __WWW__; ?>/users/create" >
        <img src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/add.png" />
        Ajouter un utilisateur
    </a>
    <table class="clementine_users_index" id="clementine_users_index">
        <thead>
            <tr>
                <th class="col1"> Nom d'utilisateur </th>
                <th class="col2"> Actions</th>
            </tr>
        </thead>
<?php 
if (isset($data['users']) && is_array($data['users']) && count($data['users'])) {
    foreach ($data['users'] as $id => $users) {
?>
        <tbody>
            <tr>
                <td class="col1"><a title="modifier" href="<?php echo __WWW__; ?>/users/edit?id=<?php echo $id; ?>" >
                        <?php echo $users['login']; ?>
                </a></td>
                <td class="col2">
                    <a title="modifier" href="<?php echo __WWW__; ?>/users/edit?id=<?php echo $id; ?>" >
                        <img src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/edit.png" />
                    </a>
                    <a title="supprimer" onclick="return(confirm('Etes-vous sûr de vouloir supprimer cet utilisateur ?'));" href="<?php echo __WWW__; ?>/users/delete?id=<?php echo $id; ?>" >
                        <img src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/delete.png" />
                    </a>
                </td>
            </tr>
<?php 
    }
} else {
?>
        <tr>
            <td colspan="4">Il n'y a aucun utilisateur. </td>
        </tr>
<?php 
}
?>
        </tbody>
    </table>
</div>
