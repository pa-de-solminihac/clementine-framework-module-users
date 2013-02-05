<?php
$ns = $this->getModel('fonctions');
?>
<div class="form_users_index">
    <a class="form_users_index_add" title="créer" href="<?php echo __WWW__; ?>/users/create" >
        <img src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/add.png" />
        Ajouter un utilisateur
    </a>
    <table class="clementine_users_index clementine-dataTables" id="clementine_users_index">
        <thead>
            <tr>
                <th class="col_login">Nom d'utilisateur </th>
<?php
        if (isset($data['show_groups'])) {
?>
                <th class="col_groups">Groupe(s)</th>
<?php
        }
        if (isset($data['show_date'])) {
?>
                <th class="col_groups">Date de création</th>
<?php
        }
        if (isset($data['show_validity'])) {
?>
                <th class="col_validity">Actif</th>
<?php
        }
?>
                <th class="col_actions">Actions</th>
            </tr>
        </thead>
<?php 
if (isset($data['users']) && is_array($data['users']) && count($data['users'])) {
$usersmodel = $this->getModel('users');
?>
        <tbody>
<?php
    foreach ($data['users'] as $id => $users) {
        if (isset($data['show_groups'])) {
            $usergroups = array_keys($usersmodel->getGroupsByUser((int) $id));
            sort($usergroups);
        }
        if (!isset($data['edit_url'])) {
            $data['edit_url'] = __WWW__ . '/users/edit';
        }
?>
            <tr>
                <td class="col_login"><a title="modifier" href="<?php echo $ns->mod_param($data['edit_url'], 'id', $id); ?>" class="<?php echo $users['active'] ? '' : 'clementine_users_disabled'; ?>" >
                        <?php echo $users['login']; ?>
                </a></td>
<?php
        if (isset($data['show_groups'])) {
?>
                <td class="col_groups"><a title="modifier" href="<?php echo $ns->mod_param($data['edit_url'], 'id', $id); ?>" >
                        <?php echo implode(',', $usergroups); ?>
                </a></td>
<?php
        }
        if (isset($data['show_date'])) {
?>
                <td class="col_date"><span style="display: none;"><?php echo $users['date_creation']; ?></span><a title="modifier" href="<?php echo $ns->mod_param($data['edit_url'], 'id', $id); ?>" >
                        <?php echo date('d/m/Y H:i:s', strtotime($users['date_creation'])); ?>
                </a></td>
<?php
        }
        if (isset($data['show_validity'])) {
?>
                <td class="col_validity"><a title="modifier" href="<?php echo $ns->mod_param($data['edit_url'], 'id', $id); ?>" >
                        <?php echo $users['active'] ? 'activé' : 'suspendu'; ?>
                </a></td>
<?php
        }
?>
                <td class="col_actions">
                    <a title="modifier" href="<?php echo $ns->mod_param($data['edit_url'], 'id', $id); ?>" >
                        <img src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/edit.png" />
                    </a>
                    <a title="supprimer" onclick="return(confirm('Etes-vous sûr de vouloir supprimer cet utilisateur ?'));" href="<?php echo __WWW__; ?>/users/delete?id=<?php echo $id; ?>" >
                        <img src="<?php echo __WWW_ROOT_USERS__; ?>/skin/images/delete.png" />
                    </a>
                </td>
            </tr>
<?php 
    }
?>
        </tbody>
<?php
} else {
?>
        <tbody>
            <tr>
                <td colspan="4">Il n'y a aucun utilisateur. </td>
            </tr>
        </tbody>
<?php
}
?>
    </table>
</div>
