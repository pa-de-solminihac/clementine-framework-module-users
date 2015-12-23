<?php
/**
 * usersUsersModel : gestion d'utilisateurs
 *
 * @package
 * @version $id$
 * @copyright
 * @author Pierre-Alexis <pa@quai13.com>
 * @license
 */
class usersUsersModel extends usersUsersModel_Parent
{

    public $table_users                 = 'clementine_users';
    public $table_users_treepaths       = 'clementine_users_treepaths';
    public $table_users_has_groups      = 'clementine_users_has_groups';
    public $table_users_has_privileges  = 'clementine_users_has_privileges';
    public $table_groups                = 'clementine_users_groups';
    public $table_groups_treepaths      = 'clementine_users_groups_treepaths';
    public $table_groups_has_privileges = 'clementine_users_groups_has_privileges';
    public $table_privileges            = 'clementine_users_privileges';

    public function _init($params = null)
    {
        $this->tables = array(
            $this->table_users => '',
            $this->table_users_has_groups => array(
                'inner join' => "`" . $this->table_users_has_groups . "`.`user_id` = `" . $this->table_users . "`.`id` "
            ) , // determine le champ AI andco_om.id
            $this->table_groups => array(
                'inner join' => "`" . $this->table_users_has_groups . "`.`group_id` = `" . $this->table_groups . "`.`id` "
            ) , // determine le champ AI andco_om.id

        );
        $this->metas['readonly_tables'] = array(
            $this->table_users_has_groups => '',
            $this->table_groups => ''
        );
        $this->group_by = array_merge($this->group_by, array(
            'user_id'
        ));
    }

    /**
     * getAuth : verifie si l'utilsateur est connecte
     *
     * @access public
     * @return void
     */
    public function getAuth()
    {
        if (!session_id()) {
            session_start();
        }
        $auth = isset($_SESSION['auth']) ? $_SESSION['auth'] : '';
        if (isset($auth['login']) && strlen($auth['login'])) {
            return $auth;
        } else {
            return false;
        }
    }

    /**
     * tryAuth : tente de se connecter avec le couple login / password passe en parametre
     *
     * @param mixed $login
     * @param mixed $password
     * @access public
     * @return void
     */
    public function tryAuth($login, $password, $params = null)
    {
        // recupere le grain de sel pour hasher le mot de passe
        $db = Clementine::getModel('db');
        $err = Clementine::getHelper('errors');
        $module_name = $this->getCurrentModule();
        $sql = '
            SELECT salt
            FROM ' . $this->table_users . '
            WHERE login = \'' . $db->escape_string($login) . '\'
            LIMIT 1 ';
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            $salt = $result[0];
            $password_hash = hash('sha256', $salt . $password);
            $sql = '
                SELECT id, login, is_alias_of
                FROM ' . $this->table_users . '
                WHERE login = \'' . $db->escape_string($login) . '\' ';
            // si le parametre bypass_login est positionne, on autorise le login sans password, et check si l'utilisateur est actif
            if (!(isset($params['bypass_login']) && $params['bypass_login'])) {
                $sql.= '
                    AND password = \'' . $db->escape_string($password_hash) . '\'
                    AND active = \'1\' ';
            }
            $sql.= '
                ORDER BY id DESC
                LIMIT 1';
            $stmt = $db->query($sql);
            $result = $db->fetch_array($stmt);
            if ($result && $result['id']) {
                if (!(isset($params['bypass_login']) && $params['bypass_login'])) {
                    // si le compte maître est suspendu, l'utilisateur ne doit plus pouvoir se connecter
                    if ($result['is_alias_of']) {
                        $user = $this->getUser($result['is_alias_of']);
                        if (!$user['active']) {
                            $err->register_err('failed_auth', 'login_error_parent', Clementine::$config['module_users']['login_error_parent'], $module_name);
                            return false;
                        }
                    }
                    // si un parent est suspendu, l'utilisateur ne doit plus pouvoir se connecter
                    $parents = Clementine::getParents($result['id']);
                    foreach ($parents as $parent) {
                        if (!$parent['active']) {
                            $err->register_err('failed_auth', 'login_error_parent', Clementine::$config['module_users']['login_error_parent'], $module_name);
                            return false;
                        }
                    }
                }
                $auth = array(
                    'id' => $result['id'],
                    'login' => $result['login']
                );
                if ($result['is_alias_of']) {
                    $auth['real_id'] = $result['is_alias_of'];
                } else {
                    $auth['real_id'] = $result['id'];
                }
                return $auth;
            } else {
                $err->register_err('failed_auth', 'login_error_password', Clementine::$config['module_users']['login_error_password'], $module_name);
                return false;
            }
        }
        $err->register_err('failed_auth', 'login_error_account', Clementine::$config['module_users']['login_error_account'], $module_name);
        return false;
    }

    /**
     * getUrlLogin : renvoie l'url pour se logguer
     *
     * @access public
     * @return void
     */
    public function getUrlLogin()
    {
        $ns = Clementine::getModel('fonctions');
        $url_retour = urldecode(Clementine::getModel('fonctions')->ifPost('html', 'url_retour', null, $_SERVER['REQUEST_URI'], 1, 1));
        // pour éviter de tourner en boucle sur la page de déconnexion
        $url_logout_relative = str_replace(__WWW__, __BASE__, $this->getUrlLogout());
        $url_retour_relative = str_replace(__WWW__, __BASE__, $url_retour);
        if ($url_retour_relative == $url_logout_relative) {
            $url_retour = __WWW__;
        }
        $url_login = $ns->mod_param(__WWW__ . '/users/login', 'url_retour', urlencode($url_retour));
        return $url_login;
    }

    /**
     * getUrlLogout : renvoie l'url pour se logguer
     *
     * @access public
     * @return void
     */
    public function getUrlLogout()
    {
        return __WWW__ . '/users/logout';
    }

    /**
     * needAuth : renvoie vers la page de login l'utilisateur n'est pas loggue
     *
     * @access public
     * @return void
     */
    public function needAuth($params = null)
    {
        $auth = $this->getAuth();
        if ($auth) {
            if (isset($params['authorized_uids'])) {
                if (in_array($auth['id'], $params['authorized_uids'])) {
                    return $auth;
                }
            } else {
                return $auth;
            }
        }
        Clementine::getModel('fonctions')->redirect($this->getUrlLogin());
    }

    /**
     * getPrivileges : renvoie la liste des privileges de l'utilisateur connecté (par défaut) sous forme d'un tableau associatif
     *
     * @param mixed $user_id : id de l'utilisateur
     * @access public
     * @return void
     */
    public function getPrivileges($user_id = null)
    {
        if (!$user_id) {
            $auth = $this->getAuth();
            $user_id = (int)$auth['id'];
        }
        if (!$user_id) {
            return array();
        }
        // pas besoin de passer par toutes les tables, on peut raccourcir les traitements en joignant seulement les tables intermediaires
        $db = Clementine::getModel('db');
        $sql = "(
            SELECT `{$this->table_privileges}`.`privilege`
            FROM `{$this->table_privileges}`
                INNER JOIN `{$this->table_groups_has_privileges}`
                    ON `{$this->table_groups_has_privileges}`.`privilege_id` = `{$this->table_privileges}`.`id`
                INNER JOIN `{$this->table_users_has_groups}`
                    ON `{$this->table_users_has_groups}`.`group_id` = `{$this->table_groups_has_privileges}`.`group_id`
            WHERE `{$this->table_users_has_groups}`.`user_id` = '" . (int)$user_id . "'
        ) UNION (
            SELECT `{$this->table_privileges}`.`privilege`
            FROM `{$this->table_privileges}`
                INNER JOIN `{$this->table_users_has_privileges}`
                    ON `{$this->table_users_has_privileges}`.`privilege_id` = `{$this->table_privileges}`.`id`
            WHERE `{$this->table_users_has_privileges}`.`user_id` = '" . (int)$user_id . "'
        ) ";
        $privileges = array();
        if ($stmt = $db->query($sql)) {
            for (true; $res = $db->fetch_assoc($stmt); true) {
                $privileges[$res['privilege']] = true;
            }
        }
        return $privileges;
    }

    /**
     * needPrivilege : renvoie vrai si l'utilisateur dispose du privilege $privilege
     *
     * @param mixed $privilege : nom du privilege requis, ou tableau représentant plusieurs privilèges : array(
     *     'privilege1' => true,
     *     'privilege2' => array(
     *         'privilege3' => true,
     *         'privilege4' => true
     *     )
     * )
     * le tableau ci-dessus sera traduit ainsi : privilege1 || privilege2 && (privilege3 || privilege4)
     * @param mixed $needauth :
     * @param mixed $specific_uid :
     * @access public
     * @return void
     */
    public function needPrivilege($privilege, $needauth = true, $specific_uid = null)
    {
        if ($needauth) {
            $this->needAuth();
        }
        if (!is_array($privilege)) {
            $privilege = array(
                $privilege => true
            );
        }
        $privileges_granted = $this->getPrivileges($specific_uid);
        $has_privilege = $this->checkPrivileges($privilege, $privileges_granted);
        if (!$has_privilege && $needauth) {
            Clementine::getModel('fonctions')->redirect($this->getUrlLogin());
        }
        return $has_privilege;
    }

    /**
     * hasPrivilege : wrapper de needPrivilege
     *
     * @param mixed $privilege : nom du privilege requis
     * @access public
     * @return void
     */
    public function hasPrivilege($privilege, $specific_uid = null)
    {
        return $this->needPrivilege($privilege, false, $specific_uid);
    }

    /**
     * addPrivilege : ajoute un privilège à la table table_privileges
     *
     * @param mixed $privilege :
     * @access public
     * @return void
     */
    public function addPrivilege($privilege)
    {
        $db = Clementine::getModel('db');
        $sql = "
            INSERT IGNORE INTO {$this->table_privileges} (
                `privilege`
            ) VALUES (
                '" . $db->escape_string($privilege) . "'
            ) ";
        return $db->query($sql);
    }

    /**
     * getUsers : recupere les couples id, login
     *
     * @param mixed $id_parent : parent a partir duquel on récupère toutes l'arborescence
     * @access public
     * @return void
     */
    public function getUsers($id = null, $max_depth = 0, $min_depth = 0, $params = null, $type = 'user', $ignore_aliases = true)
    {
        $id = (int)$id;
        $table = $this->table_groups;
        if ($type == 'user') {
            $table = $this->table_users;
        }
        $db = Clementine::getModel('db');
        $sql = "
            SELECT `" . $table . "`.*, depth
            FROM `" . $table . "`
                INNER JOIN `" . $table . "_treepaths`
                    ON `" . $table . "`.id = `" . $table . "_treepaths`.`descendant`
            WHERE 1 ";
        if ($id) {
            $sql.= "
                AND `" . $table . "_treepaths`.`ancestor` = " . (int)$id . " ";
        }
        // ignore les utilisateurs alias
        if ($ignore_aliases) {
            $sql.= "
                AND is_alias_of IS NULL ";
        }
        // par defaut on renvoie tous les enfants
        if ($max_depth) {
            $sql.= "
                AND `depth` <= " . (int)$max_depth . " ";
        }
        if ($min_depth) {
            $sql.= "
                AND `depth` >= " . (int)$min_depth . " ";
        }
        if (isset($params['where'])) {
            $sql.= '
                AND ' . $params['where'] . ' ';
        }
        if (isset($params['order_by'])) {
            $sql.= '
                ORDER BY ' . $params['order_by'] . ' ';
        } else {
            $sql.= '
                ORDER BY `login` ';
        }
        $enfants = array();
        if ($stmt = $db->query($sql)) {
            for (true; $res = $db->fetch_assoc($stmt); true) {
                $enfants[$res['id']] = $res;
            }
        }
        return $enfants;
    }

    /**
     * getUsersByGroup : recupere la liste des id et login en fonction du groupe
     *
     * @access public
     * @return void
     */
    public function getUsersByGroup($id_group)
    {
        $id_group = (int)$id_group;
        $db = Clementine::getModel('db');
        $sql = "
            SELECT `" . $this->table_users . "`.*
            FROM `" . $this->table_users . "`
                LEFT JOIN `" . $this->table_users_has_groups . "`
                    ON `user_id` = `" . $this->table_users . "`.`id`
                LEFT JOIN `" . $this->table_groups . "`
                    ON `group_id` = `" . $this->table_groups . "`.`id`
            WHERE `" . $this->table_groups . "`.`id` = '" . $id_group . "'
            ORDER BY login ";
        $stmt = $db->query($sql);
        $users = array();
        for (; $res = $db->fetch_assoc($stmt); true) {
            $users[$res['id']] = $res;
        }
        return $users;
    }

    /**
     * getUser : récupère les infos d'un user
     *
     * @param mixed $id
     * @access public
     * @return void
     */
    public function getUser($id, $more_details = false)
    {
        $id = (int)$id;
        $db = Clementine::getModel('db');
        if (!$more_details) {
            $sql = "
                SELECT *
                FROM `" . $this->table_users . "`
                WHERE `id` = '" . (int)$id . "'
                LIMIT 1 ";
        } else {
            $sql = "
                SELECT *
                FROM `" . $this->table_users . "`
                    INNER JOIN `" . $this->table_users_treepaths . "`
                        ON `" . $this->table_users . "`.id = `" . $this->table_users_treepaths . "`.`descendant`
                WHERE `" . $this->table_users . "`.`id` = '" . (int)$id . "'
                LIMIT 1 ";
        }
        $stmt = $db->query($sql);
        $user = $db->fetch_assoc($stmt);
        return $user;
    }

    /**
     * getGroup : récupère le groupe correspondant à un id
     *
     * @param mixed $id
     * @access public
     * @return void
     */
    public function getGroup($id)
    {
        $id = (int)$id;
        $db = Clementine::getModel('db');
        $sql = "
            SELECT *
            FROM `" . $this->table_groups . "`
            WHERE `id` = '" . $id . "'
            LIMIT 1 ";
        $stmt = $db->query($sql);
        if ($stmt) {
            return $db->fetch_assoc($stmt);
        }
        return false;
    }

    public function getGroupParents($id, $max_depth = 0, $min_depth = 0)
    {
        return Clementine::getParents($id, $max_depth, $min_depth, 'group');
    }

    /**
     * getParents : renvoie les parents en respectant l'ordre remontant dans la hierarchie (ORDER BY depth ASC)
     *
     * @param mixed $id
     * @param int $max_depth
     * @param int $min_depth
     * @param string $type
     * @param mixed $ignore_aliases
     * @access public
     * @return void
     */
    public function getParents($id, $max_depth = 0, $min_depth = 0, $type = 'user', $ignore_aliases = true)
    {
        $id = (int)$id;
        switch ($type) {
        case 'user':
            $table = $this->table_users;
            break;

        default:
            $table = $this->table_groups;
            break;
        }
        $db = Clementine::getModel('db');
        $sql = "
            SELECT `" . $table . "`.*, depth
            FROM `" . $table . "`
                INNER JOIN `" . $table . "_treepaths`
                    ON `" . $table . "`.id = `" . $table . "_treepaths`.`ancestor`
            WHERE `" . $table . "_treepaths`.`descendant` = " . (int)$id . "
                AND `" . $table . "_treepaths`.`ancestor` != `" . $table . "_treepaths`.`descendant` ";
        // ignore les utilisateurs alias
        if ($ignore_aliases) {
            $sql.= "
                AND is_alias_of IS NULL ";
        }
        // par defaut on renvoie tous les parents
        if ($max_depth) {
            $sql.= "
                AND `depth` <= " . (int)$max_depth . " ";
        }
        if ($min_depth) {
            $sql.= "
                AND `depth` >= " . (int)$min_depth . " ";
        }
        $sql.= "
            ORDER BY depth ";
        $parents = array();
        if ($stmt = $db->query($sql)) {
            for (true; $res = $db->fetch_assoc($stmt); true) {
                $parents[$res['id']] = $res;
            }
        }
        return $parents;
    }

    /**
     * getRootParent : renvoie le parent racine, le plus haut de la hiérarchie
     *
     * @param mixed $id
     * @param string $type
     * @param mixed $ignore_aliases
     * @access public
     * @return void
     */
    public function getRootParent($id, $type = 'user', $ignore_aliases = true)
    {
        $id = (int)$id;
        switch ($type) {
        case 'user':
            $table = $this->table_users;
            break;

        default:
            $table = $this->table_groups;
            break;
        }
        $db = Clementine::getModel('db');
        $sql = "
            SELECT `" . $table . "`.*, depth
            FROM `" . $table . "`
                INNER JOIN `" . $table . "_treepaths`
                    ON `" . $table . "`.id = `" . $table . "_treepaths`.`ancestor`
            WHERE `" . $table . "_treepaths`.`descendant` = " . (int)$id . " ";
        // ignore les utilisateurs alias
        if ($ignore_aliases) {
            $sql.= "
                AND is_alias_of IS NULL ";
        }
        // on renvoie le parent racine
        $sql.= "
            ORDER BY depth DESC LIMIT 1 ";
        if ($stmt = $db->query($sql)) {
            return $db->fetch_assoc($stmt);
        }
        return false;
    }

    public function getRoots($params = null, $type = 'user', $ignore_aliases = true, $filter_by_group_ids = null)
    {
        $table = $this->table_groups;
        $table_treepaths = $this->table_groups_treepaths;
        if ($type == 'user') {
            $table = $this->table_users;
            $table_treepaths = $this->table_users_treepaths;
        }
        $db = Clementine::getModel('db');
        $sql = "
            SELECT `" . $table . "`.*, t1.*, t2.*
            FROM `" . $table . "`
                INNER JOIN `" . $table_treepaths . "` t1
                    ON `" . $table . "`.id = t1.`ancestor`
                    AND t1.depth = 0
                LEFT JOIN `" . $table_treepaths . "` t2
                    ON `" . $table . "`.id = t2.`descendant`
                    AND t2.depth <> 0 ";
        // filtre par groupes
        if ($type = 'user' && $filter_by_group_ids) {
            $groups_list = implode(", ", array_map('intval', (array)$filter_by_group_ids));
            $sql.= "
                INNER JOIN `" . $this->table_users_has_groups . "`
                    ON `" . $table . "`.id = `" . $this->table_users_has_groups . "`.`user_id`
                    AND group_id IN (" . $groups_list . ")";
        }
        $sql.= "
            WHERE t2.ancestor IS NULL ";
        // ignore les utilisateurs alias
        if ($type = 'user' && $ignore_aliases) {
            $sql.= "
                AND is_alias_of IS NULL ";
        }
        if (isset($params['where'])) {
            $sql.= "
                AND ${params['where']} ";
        }
        $sql.= "
            ORDER BY id ";
        $roots = array();
        if ($stmt = $db->query($sql)) {
            for (true; $res = $db->fetch_assoc($stmt); true) {
                $roots[$res['id']] = $res;
            }
        }
        return $roots;
    }

    /**
     * getGroupsByUser : récupère les groupes d'un user
     *
     * @param mixed $id
     * @access public
     * @return void
     */
    public function getGroupsByUser($id)
    {
        $id = (int)$id;
        $db = Clementine::getModel('db');
        $sql = '
            SELECT `' . $this->table_groups . '`.`group`
            FROM ' . $this->table_groups . '
                LEFT JOIN  `' . $this->table_users_has_groups . '`
                    ON `' . $this->table_users_has_groups . '`.`group_id` = `' . $this->table_groups . '`.`id`
            WHERE `' . $this->table_users_has_groups . '`.`user_id` = \'' . $id . '\'';
        $stmt = $db->query($sql);
        $groups = array();
        for (; $res = $db->fetch_array($stmt); true) {
            $groups[$res['group']] = $res;
        }
        return $groups;
    }

    /**
     * getUserByLogin : récupère les infos d'un user
     *
     * @param mixed $login
     * @access public
     * @return void
     */
    public function getUserByLogin($login)
    {
        $db = Clementine::getModel('db');
        $sql = '
            SELECT *
            FROM ' . $this->table_users . '
            WHERE login = \'' . $db->escape_string($login) . '\'
            LIMIT 1 ';
        $stmt = $db->query($sql);
        $user = $db->fetch_assoc($stmt);
        return $user;
    }

    public function create($insecure_values, $params = null)
    {
        $ns = Clementine::getModel('fonctions');
        $user = $this->getUserByLogin($insecure_values[$this->table_users . '-login']);
        if ($user) {
            return false;
        }
        $db = Clementine::getModel('db');
        if (empty($params['dont_start_transaction'])) {
            $db->query('START TRANSACTION');
        }
        // force la date de création
        $date = date('Y-m-d H:i:s');
        $insecure_values[$this->table_users . '-date_modification'] = $date;
        $insecure_values[$this->table_users . '-date_creation'] = $date;
        // parent:: but dont start transaction
        $fake_params = $params;
        $fake_params['dont_start_transaction'] = true;
        if (!$last_insert_ids = parent::create($insecure_values, $fake_params)) {
            $db->query('ROLLBACK');
            return false;
        }
        $last_insert_id = $last_insert_ids[$this->table_users]['id'];
        $sql = "
            INSERT INTO {$this->table_users_treepaths} (
                `ancestor`,
                `descendant`,
                `depth`
            ) VALUES (
                '$last_insert_id',
                '$last_insert_id',
                0
            )";
        if (!$stmt = $db->query($sql)) {
            $db->query('ROLLBACK');
            return false;
        }
        // on rattache l'utilisateur si c'est une création par un utilisateur connecté
        $auth = $this->getAuth();
        if (isset($auth['login']) && strlen($auth['login']) && !isset($user['id'])) {
            // si c'est un adjoint on le rattache au meme parent que le compte maitre
            if (isset($params['adjoint']) && $params['adjoint']) {
                $parents_directs = Clementine::getParents($auth['id'], 1, 1);
                $parent_direct = false;
                if (count($parents_directs)) {
                    $parent_direct = $ns->array_first($parents_directs);
                }
                if ($parent_direct && isset($parent_direct['id']) && $parent_direct['id']) {
                    $id_parent = $parent_direct['id'];
                } else {
                    // pas de parent, on ne rattache pas
                    $id_parent = 0;
                }
            } else {
                $id_parent = $auth['id'];
            }
        } else {
            if (!isset($user['id'])) {
                $id_parent = 0;
            } else {
                // en cas de modif, on garde l'id parent existant
                $parents_directs = Clementine::getParents($user['id'], 1, 1);
                $parent_direct = false;
                if (count($parents_directs)) {
                    $parent_direct = $ns->array_first($parents_directs);
                }
                if ($parent_direct && isset($parent_direct['id']) && $parent_direct['id']) {
                    $id_parent = $parent_direct['id'];
                }
            }
        }
        if ($id_parent) {
            if (!$this->updateParent($last_insert_id, $id_parent)) {
                $db->query('ROLLBACK');
                return false;
            }
        }
        if (!empty($params['default_group'])) {
            if ($group = $this->getGroupByName($params['default_group'])) {
                $this->addUserToGroup($last_insert_id, $group['id']);
            }
        }
        // on fait un update de l'utilisateur pour finaliser la création
        $insecure_primary_key = array(
            $this->table_users . '-id' => $last_insert_id
        );
        $this->update($insecure_values, $insecure_primary_key, $params);
        if (empty($params['dont_start_transaction'])) {
            $db->query('COMMIT');
        }
        return $last_insert_ids;
    }

    public function update($insecure_values, $insecure_primary_key = null, $params = null)
    {
        $ns = Clementine::getModel('fonctions');
        $id = (int)$insecure_primary_key[$this->table_users . '-id'];
        $user = $this->getUser($id);
        if (!$user) {
            return false;
        }
        // force la date de création
        $date = date('Y-m-d H:i:s');
        $insecure_values[$this->table_users . '-date_modification'] = $date;
        if (!empty($insecure_values['mot_de_passe']) && $insecure_values['mot_de_passe'] != 'password') {
            // genere un grain de sel aleatoire
            $insecure_values[$this->table_users . '-salt'] = hash('sha256', (microtime() . rand(0, getrandmax())));
            // hash le password avec le grain de sel
            $insecure_values[$this->table_users . '-password'] = hash('sha256', $insecure_values[$this->table_users . '-salt'] . $insecure_values['mot_de_passe']);
        }
        // genere un code de confirmation aleatoire
        $insecure_values[$this->table_users . '-code_confirmation'] = hash('sha256', (microtime() . rand(0, getrandmax())));
        // met a jour les champs en base de donnees
        $db = Clementine::getModel('db');
        if (empty($params['dont_start_transaction'])) {
            $db->query('START TRANSACTION');
        }
        // parent:: but dont start transaction
        $fake_params = $params;
        $fake_params['dont_start_transaction'] = true;
        if (!$ret = parent::update($insecure_values, $insecure_primary_key, $fake_params)) {
            $db->query('ROLLBACK');
            return false;
        }
        if (empty($params['dont_start_transaction'])) {
            $db->query('COMMIT');
        }
        return $ret;
    }

    /**
     * delUser : supprime un user
     *
     * @param mixed $id
     * @access public
     * @return void
     */
    public function delUser($id)
    {
        $id = (int)$id;
        if ($id) {
            $db = Clementine::getModel('db');
            $sql = "DELETE FROM " . $this->table_users_has_groups . " WHERE `user_id` = '$id' ";
            $stmt = $db->query($sql);
            $sql = "DELETE FROM " . $this->table_users . " WHERE `id` = '$id' LIMIT 1 ";
            $stmt = $db->query($sql);
            if ($db->affected_rows()) {
                return true;
            }
            return false;
        }
    }

    /**
     * updatePassword : remplace le mot de passe de l'utilisateur $login par $password (en le cryptant et en mettant a jour les meta donnees associees)
     *
     * @param mixed $login
     * @param mixed $password
     * @access public
     * @return void
     */
    public function updatePassword($login, $password)
    {
        $salt = hash('sha256', (microtime() . rand(0, getrandmax())));
        $code_confirmation = hash('sha256', (microtime() . rand(0, getrandmax())));
        // hash le password avec le grain de sel
        $password_hash = hash('sha256', $salt . $password);
        $date = date('Y-m-d H:i:s');
        $db = Clementine::getModel('db');
        $sql = "UPDATE " . $this->table_users . "
                    SET `password`          = '" . $db->escape_string($password_hash) . "',
                        `salt`              = '" . $salt . "',
                        `code_confirmation` = '" . $code_confirmation . "',
                        `date_modification` = '" . $db->escape_string($date) . "'
                  WHERE `login` = '" . $db->escape_string($login) . "'
                  LIMIT 1 ";
        $stmt = $db->query($sql);
        return $stmt;
    }

    /**
     * addUserToGroup : ajoute un utilisateur à un groupe
     *
     * @param mixed $id
     * @param mixed $group_id
     * @access public
     * @return void
     */
    public function addUserToGroup($id, $group_id)
    {
        $id = (int)$id;
        $group_id = (int)$group_id;
        $user = $this->getUser($id);
        if ($user) {
            $db = Clementine::getModel('db');
            $sql = "INSERT INTO `" . $this->table_users_has_groups . "` (user_id, group_id)
                    VALUES ('" . $id . "', '" . $group_id . "')";
            return $db->query($sql);
        }
        return false;
    }

    /**
     * delUserFromGroup : enlève un utilisateur d'un groupe
     *
     * @author Julien Malandain <julien@quai13.com>
     * @param mixed $id
     * @param mixed $group_id
     * @access public
     * @return void
     */
    public function delUserFromGroup($id, $group_id)
    {
        $id = (int)$id;
        $group_id = (int)$group_id;
        $user = $this->getUser($id);
        if ($user) {
            $db = Clementine::getModel('db');
            $sql = "DELETE FROM `" . $this->table_users_has_groups . "`
                WHERE `user_id` = '" . $id . "'
                AND `group_id` = '" . $group_id . "'
                LIMIT 1 ";
            return $db->query($sql);
        }
        return false;
    }

    /**
     * getGroupByName : renvoie le du groupe en fonction de son nom
     *
     * @param mixed $group_name
     * @access public
     * @return void
     */
    public function getGroupByName($group)
    {
        $db = Clementine::getModel('db');
        $sql = "
            SELECT id
            FROM `" . $this->table_groups . "`
            WHERE `group` = '" . $db->escape_string($group) . "' ";
        $stmt = $db->query($sql);
        if ($db->num_rows($stmt)) {
            return $db->fetch_assoc($stmt);
        } else {
            return false;
        }
    }

    /**
     * addGroup : cree un nouveau groupe s'il n'existe pas et renvoie son id (false sinon)
     *
     * @param mixed $name
     * @access public
     * @return void
     */
    public function addGroup($name, $id_parent = null, $params = null)
    {
        // insertion du user en 2 temps : insertion minimaliste, et update du user dans un 2e temps (moins performant mais factorise le code)
        $group = $this->getGroupByName($name);
        if (!$group) {
            $db = Clementine::getModel('db');
            if (empty($params['dont_start_transaction'])) {
                $db->query('START TRANSACTION');
            }
            $sql = "INSERT INTO `" . $this->table_groups . "` (`id`, `group`) VALUES (NULL, '" . $db->escape_string($name) . "')";
            if (!$stmt = $db->query($sql)) {
                $db->query('ROLLBACK');
                return false;
            }
            $last_insert_id = $db->insert_id();
            $sql = "INSERT INTO " . $this->table_groups_treepaths . " (`ancestor`, `descendant`, `depth`) VALUES ('" . (int)$last_insert_id . "', '" . (int)$last_insert_id . "', 0)";
            if (!$stmt = $db->query($sql)) {
                $db->query('ROLLBACK');
                return false;
            }
            if ($id_parent) {
                if (!$this->updateGroupParent($last_insert_id, $id_parent)) {
                    $db->query('ROLLBACK');
                    return false;
                }
            }
            if (empty($params['dont_start_transaction'])) {
                $db->query('COMMIT');
            }
            return $last_insert_id;
        }
        return false;
    }

    /**
     * modGroup : modifie un groupe avec le tableau associatif passe en parametre
     *
     * @param mixed $id
     * @param mixed $donnees
     * @access public
     * @return void
     */
    public function modGroup($id, $donnees, $params = null)
    {
        $id = (int)$id;
        $group = $this->getGroup($id);
        if ($group) {
            $group_original = $group;
            // ecrase les donnees chargees avec celles mises à jour
            foreach ($donnees as $key => $val) {
                $group[$key] = $val;
            }
            if ($group) {
                $db = Clementine::getModel('db');
                if (empty($params['dont_start_transaction'])) {
                    $db->query('START TRANSACTION');
                }
                $sql = "UPDATE " . $this->table_groups . "
                            SET `group`             = '" . $db->escape_string($group['group']) . "',
                          WHERE `id` = '" . (int)$id . "'
                          LIMIT 1 ";
                if (!$stmt = $db->query($sql)) {
                    $db->query('ROLLBACK');
                    return false;
                }
                if ($group_original['id_parent'] != $group['id_parent']) {
                    $this->updateGroupParent($id, $group['id_parent']);
                }
                if (empty($params['dont_start_transaction'])) {
                    $db->query('COMMIT');
                }
                return $group;
            }
        }
        return false;
    }

    public function updateParent($id, $id_parent, $type = 'user')
    {
        if ($id == $id_parent) {
            return false;
        }
        switch ($type) {
        case 'user':
            $table = $this->table_users_treepaths;
            break;

        default:
            $table = $this->table_groups_treepaths;
            break;
        }
        $id = (int)$id;
        $id_parent = (int)$id_parent;
        $db = Clementine::getModel('db');
        $enfants = $this->getUsers($id, null, 1, null, 'user', false);
        $sql = "
            DELETE FROM `" . $table . "`
            WHERE `descendant` = '" . (int)$id . "'
                AND `depth` != 0 ";
        if ($stmt = $db->query($sql)) {
            if ($id_parent) {
                // rattache en regénérant l'arborescence
                $sql = "
                    INSERT INTO `" . $table . "` (`ancestor`, `descendant`, `depth`)
                        SELECT ancestor, '" . (int)$id . "', (depth + 1)
                        FROM `" . $table . "`
                        WHERE `descendant` = '" . (int)$id_parent . "' ";
                $stmt = $db->query($sql);
                // met a jour récursivement TOUS les enfants de cet utilisateur en consequence
                foreach ($enfants as $enfant) {
                    // sinon on MAJ l'ancestor sans changer la profondeur puisqu'elle est relative
                    $this->updateParent($enfant['id'], $id, $type);
                }
                return $stmt;
            }
            return $stmt;
        }
        return false;
    }

    public function updateGroupParent($id, $id_parent)
    {
        return $this->updateParent($id, $id_parent, 'group');
    }

    /**
     * sanitize : recupere et assainit les donnees
     *
     * @param mixed $insecure_array
     * @access public
     * @return void
     */
    public function sanitize($insecure_array)
    {
        $ns = Clementine::getModel('fonctions');
        $secure_array = array();
        if (isset($insecure_array['password']) && ($insecure_array['password'] != 'password')) {
            $secure_array['password'] = $ns->strip_tags($insecure_array['password']);
        } else {
            if (isset($secure_array['password'])) {
                unset($secure_array['password']);
            }
        }
        if (isset($insecure_array['password_conf']) && ($insecure_array['password_conf'] != 'password')) {
            $secure_array['password_conf'] = $ns->strip_tags($insecure_array['password_conf']);
        } else {
            if (isset($secure_array['password_conf'])) {
                unset($secure_array['password_conf']);
            }
        }
        if (isset($insecure_array['login'])) {
            $secure_array['login'] = $ns->strip_tags($insecure_array['login']);
        }
        return $secure_array;
    }

    public function checkPrivileges($required_privileges_tree, $my_privileges = array())
    {
        $condition = $this->checkConditions_translate($required_privileges_tree, $my_privileges);
        return (eval('return ' . $condition . ';'));
    }

    public function checkConditions_translate($required_privileges_tree, $my_privileges = array() , $debug = false)
    {
        $str = '';
        $nb_parentheses = 0;
        if (is_array($required_privileges_tree)) {
            $i = 0;
            foreach ($required_privileges_tree as $privilege => $moreprivileges) {
                if ($i && $str) {
                    $str.= ' || ';
                }
                if ($moreprivileges !== false) {
                    if (substr($privilege, 0, 1) == '!') {
                        $privilege = trim(substr($privilege, 1));
                        if ($debug) {
                            $str.= "!array_key_exists('" . $privilege . "', \$my_privileges)";
                        } else {
                            if (!array_key_exists($privilege, $my_privileges)) {
                                $str.= 'true';
                            } else {
                                $str.= 'false';
                            }
                        }
                    } else {
                        if ($debug) {
                            $str.= "array_key_exists('" . $privilege . "', \$my_privileges)";
                        } else {
                            if (array_key_exists($privilege, $my_privileges)) {
                                $str.= 'true';
                            } else {
                                $str.= 'false';
                            }
                        }
                    }
                } else {
                    if ($debug) {
                        $str.= "!array_key_exists('" . $privilege . "', \$my_privileges)";
                    } else {
                        if (!array_key_exists($privilege, $my_privileges)) {
                            $str.= 'true';
                        } else {
                            $str.= 'false';
                        }
                    }
                }
                if (is_array($moreprivileges)) {
                    if (count($moreprivileges)) {
                        $str.= ' && ';
                        if (count($moreprivileges) > 1) {
                            ++$nb_parentheses;
                            $str.= '(';
                        }
                        $str.= $this->checkConditions_translate($moreprivileges, $my_privileges, $debug);
                    }
                }
                if ($nb_parentheses) {
                    --$nb_parentheses;
                    $str.= ')';
                }
                ++$i;
            }
        }
        return $str;
    }

    /**
     * isParent : returns true if $id_parent is parent of $id_child
     *
     * @param mixed $id_parent
     * @param mixed $id_child
     * @param mixed $depth
     * @access public
     * @return void
     */
    public function isParent($id_parent, $id_child, $depth = 0)
    {
        $db = Clementine::getModel('db');
        $sql = "
            SELECT depth
            FROM " . $this->table_users_treepaths . "
            WHERE ancestor   = '" . (int)$id_parent . "'
                AND descendant = '" . (int)$id_child . "'
                AND depth != 0 ";
        if ($depth) {
            $sql.= "
                AND depth <= " . (int)$depth . " ";
        }
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * isChild returns true if $id_child is child of $id_parent
     *
     * @param mixed $id_child
     * @param mixed $id_parent
     * @param int $depth
     * @access public
     * @return void
     */
    public function isChild($id_child, $id_parent, $depth = 0)
    {
        return $this->isParent($id_parent, $id_child, $depth);
    }

    /**
     * isAlias : returns true if id_alias and id_user are aliases
     *
     * @param mixed $user_id
     * @param mixed $alias_id
     * @param mixed $strict : returns true only if id_alias === is_alias_of field of user id_user
     * @access public
     * @return void
     */
    public function isAlias($id_alias, $id_user, $strict = false)
    {
        if ($id_alias == $id_user) {
            return false;
        }
        $db = Clementine::getModel('db');
        $sql = "
            SELECT id
            FROM " . $this->table_users . "
            WHERE (
                (is_alias_of = '" . (int)$id_user . "'
                    AND id = '" . (int)$id_alias . "') ";
        if (!$strict) {
            $sql.= "
             OR (is_alias_of = '" . (int)$id_alias . "'
                AND id = '" . (int)$id_user . "')
             OR (
                SELECT id
                FROM " . $this->table_users . "
                WHERE (id = '" . (int)$id_alias . "'
                    AND is_alias_of IN (
                        SELECT is_alias_of
                        FROM " . $this->table_users . "
                        WHERE id = '" . (int)$id_user . "'
                    )
                )
            ) ";
        }
        $sql.= ')';
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * isSibling : returns true if $id_user is sibling of $id_sibling
     *
     * @param mixed $id_user
     * @param mixed $id_sibling
     * @param mixed $strict : if false, cousins will be considered as siblings too
     * @access public
     * @return void
     */
    public function isSibling($id_user, $id_sibling, $strict = 1)
    {
        if ($id_sibling == $id_user) {
            return false;
        }
        $db = Clementine::getModel('db');
        $sql = "
            SELECT depth
            FROM " . $this->table_users_treepaths . "
            WHERE descendant = '" . (int)$id_user . "'
                AND CONCAT(ancestor, '-', depth) IN (
                    SELECT CONCAT(ancestor, '-', depth)
                    FROM " . $this->table_users_treepaths . "
                    WHERE descendant = '" . (int)$id_sibling . "' ";
        if ($strict) {
            $sql.= "
                        AND depth = 1 ";
        }
        $sql.= "
                ) ";
        if ($strict) {
            $sql.= "
                AND depth = 1 ";
        }
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            if (!$this->isAlias($id_user, $id_sibling)) {
                return true;
            }
        }
        return false;
    }

}
?>
