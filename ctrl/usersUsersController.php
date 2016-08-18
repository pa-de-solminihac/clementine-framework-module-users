<?php
/**
 * usersUsersController : gestion d'utilisateurs et d'authentification
 *
 * @package
 * @version $id$
 * @copyright
 * @author Pierre-Alexis <pa@quai13.com>
 * @license
 */
class usersUsersController extends usersUsersController_Parent
{

    /**
     * indexAction : liste des utilisateurs
     *
     * @access public
     * @return void
     */
    public function indexAction($request, $params = null)
    {
        // require privilege "list_users"
        $users = $this->_crud;
        if (!$users->hasPrivilege('list_users')) {
            $this->getModel('fonctions')->redirect($users->getUrlLogin());
        }
        $conf = $this->getModuleConfig();
        // options d'affichage
        $this->hideAllFields();
        $this->unhideField($users->table_users . '.login');
        // show_groups
        $show_groups = 0;
        if (!empty($params['show_groups'])) {
            $show_groups = $params['show_groups'];
        } else if (!empty($conf['show_groups'])) {
            $show_groups = $conf['show_groups'];
        }
        if ($show_groups) {
            $this->data['show_groups'] = $show_groups;
            $this->addField('Groupes', null, array(
                'sql_definition' => 'GROUP_CONCAT(DISTINCT `group` ORDER BY `group` SEPARATOR ", ")',
                'custom_search' => '`group`'
            ));
        }
        // show_validity
        $show_validity = 0;
        if (!empty($params['show_validity'])) {
            $show_validity = $params['show_validity'];
        } else if (!empty($conf['show_validity'])) {
            $show_validity = $conf['show_validity'];
        }
        if ($show_validity) {
            $this->data['show_validity'] = $show_validity;
            $this->unhideField($users->table_users . '.active');
            $this->overrideField($users->table_users . '.active', array(
                'type' => 'boolean'
            ));
        }
        // show_date
        $show_date = 0;
        if (!empty($params['show_date'])) {
            $show_date = $params['show_date'];
        } else if (!empty($conf['show_date'])) {
            $show_date = $conf['show_date'];
        }
        if ($show_date) {
            $this->data['show_date'] = $show_date;
            $this->unhideField($users->table_users . '.date_creation');
        }
        // simulate_users
        $simulate_users = 0;
        if (!empty($params['simulate_users'])) {
            $simulate_users = $params['simulate_users'];
        } else if (!empty($conf['simulate_users'])) {
            $simulate_users = $conf['simulate_users'];
        }
        if ($simulate_users) {
            if ($users->hasPrivilege('manage_users')) {
                $this->data['simulate_users'] = $simulate_users;
            }
        }
        $ret = parent::indexAction($request, $params);
        // formtype
        $formtype = 'update';
        if (!empty($params['formtype'])) {
            $formtype = $params['formtype'];
        } else if (!empty($conf['formtype'])) {
            $formtype = $conf['formtype'];
        }
        $this->data['formtype'] = $formtype;
        return $ret;
    }

    /**
     * loginAction : login de l'utilisateur si POST et redirige l'utilisateur vers la page appelante si OK. Affiche le formulaire de login sinon (si pas ok, ou si pas de POST).
     *
     * @access public
     * @return void
     */
    public function loginAction($request, $params = null)
    {
        $ns = $this->getModel('fonctions');
        // Traitement de la demande de login
        $url_retour = $this->getModel('fonctions')->ifGet('html', 'url_retour', null, __WWW__, 1, 1);
        if (!empty($_POST)) {
            // collect the data from the user
            $login = $ns->strip_tags($request->POST['login']);
            $password = $ns->strip_tags($request->POST['password']);
            if (empty($login)) {
                $this->data['message'] = 'vous devez fournir vos identifiants';
            } else {
                $this->login($login, $password, array(
                    'url_retour' => $url_retour
                ));
            }
        }
        // NOTE : on ne redirige pas si l'utilisateur est deja authentifie...
        // car on créerait une boucle de redirection
        // (puisqu'il n'aurait pas du être redirigé ici)
        // render de la vue
        $this->data['url_retour'] = $url_retour;
    }

    /**
     * simulateAction : permet a un admin de se connecter "en tant que" un autre utilisateur
     *
     * @param mixed $params
     * @access public
     * @return void
     */
    public function simulateAction($request, $params = null)
    {
        // accessible aux admin uniquement
        $users = $this->_crud;
        $users->needPrivilege('manage_users');
        // récupère les infos de utilisateur à "usurper"
        $id = $request->get('int', 'id');
        $user_to_be = $users->getUser($id);
        // on n'autorise à simuler un utilisateur que si lui-même n'a pas accès à cette fonctionnalité
        if (!$users->hasPrivilege('manage_users', $user_to_be['id'])) {
            if ($user_to_be && isset($user_to_be['login']) && $user_to_be['login']) {
                $params['bypass_login'] = 1;
                $params['simulate_user'] = 1;
                if (!isset($params['url_retour'])) {
                    $params['url_retour'] = __WWW__;
                }
                $this->login($user_to_be['login'], null, $params);
            }
        } else {
            $this->getModel('fonctions')->redirect(__WWW__);
        }
        return array(
            'dont_getblock' => true
        );
    }

    public function login($login, $password = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        // tente l'authentification
        if (isset($params['simulate_user']) && $params['simulate_user']) {
            if (isset($_SESSION['previous_auth'])) {
                unset($_SESSION['previous_auth']);
            }
            $previous_auth = $this->_crud->getAuth();
        }
        $module_name = $this->getCurrentModule();
        $err = $this->getHelper('errors');
        $err->flush($module_name);
        $auth = $this->_crud->tryAuth($login, $password, $params);
        $auth_errors = $err->get($module_name, 'failed_auth');
        // recrée la session si on est en train de simuler un utilisateur
        if ($auth && isset($params['bypass_login']) && $params['bypass_login']) {
            $this->logout($params);
        }
        // recuperation de l'url retour
        if (!session_id()) {
            session_start();
        } else {
            session_regenerate_id(true);
        }
        if ($auth) {
            $_SESSION['auth'] = $auth;
            if (isset($params['simulate_user']) && $params['simulate_user']) {
                $_SESSION['previous_auth'] = $previous_auth;
            }
            if (isset($params['url_retour'])) {
                $ns->redirect($params['url_retour']);
            }
        } else {
            // failure: clear auth from session
            if (isset($_SESSION['auth'])) {
                unset($_SESSION['auth']);
            }
            if ($auth_errors) {
                $this->data['errors'] = $auth_errors;
                $this->data['message'] = implode('<br />', $auth_errors);
            }
            // header 403 puisqu'accès refusé
            header('HTTP/1.0 403 Forbidden');
        }
        return false;
    }

    /**
     * logoutAction : page de logout de l'utilisateur
     *
     * @access public
     * @return void
     */
    public function logoutAction($request, $params = null)
    {
        $user_to_be = null;
        // prépare les infos si besoin d'un retour a l'utilisateur qu'on etait avant le simulate
        if (isset($_SESSION['previous_auth'])) {
            $user_to_be = $_SESSION['previous_auth'];
            $params['bypass_login'] = 1;
            if (!isset($params['url_retour'])) {
                $params['url_retour'] = __WWW__;
            }
        }
        $this->logout($params);
        // retour a l'utilisateur qu'on etait avant le simulate
        if ($user_to_be) {
            $this->login($user_to_be['login'], null, $params);
        }
        if (isset($params['url_retour'])) {
            $this->getModel('fonctions')->redirect($params['url_retour']);
        }
    }

    /**
     * logout : logout de l'utilisateur
     *
     * @access public
     * @return void
     */
    public function logout($params = null)
    {
        if (!session_id()) {
            session_start();
        }
        if (!empty($params['bypass_login'])) {
            if (isset($_SESSION['auth'])) {
                unset($_SESSION['auth']);
            }
            session_unset();
        } else {
            // Unset all of the session variables.
            $_SESSION = array();
            // If it's desired to kill the session, also delete the session cookie.
            // Note: This will destroy the session, and not just the session data!
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            // Finally, destroy the session.
            session_destroy();
        }
    }

    public function rename_fields($request, $params = null)
    {
        $ret = parent::rename_fields($request, $params);
        //$this->mapFieldName($this->_crud->table_users . '.id', 'N<sup>o</sup>');
        $this->mapFieldName($this->_crud->table_users . '.login', 'Adresse e-mail');
        $this->mapFieldName($this->_crud->table_users . '.date_creation', 'Date de création');
        $this->mapFieldName('custom_date_creation', 'Date de création');
        return $ret;
    }

    /**
     * deleteAction : prepare les variables pour la suppression d'un user
     *
     * @access public
     * @return void
     */
    public function deleteAction($request, $params = null)
    {
        $users = $this->_crud;
        if (isset($params['user_id'])) {
            $id = $params['user_id'];
        } else {
            $id = $request->get('int', $users->table_users . '-id');
        }
        if (empty($params['url_retour'])) {
            $params['url_retour'] = __WWW__ . '/users';
        }
        $auth = $users->needAuth();
        // recupere les données
        $user = $users->getUser($id);
        if ($user['id'] && !($users->hasPrivilege('manage_users') || isset($params['allow_login_modification']))) {
            $users->needPrivilege('manage_users');
        }
        $success = false;
        if ($id != $auth['id']) {
            // suppression du user
            if ($id) {
                if ($success = $this->_crud->delUser($id)) {
                    $ns = $this->getModel('fonctions');
                    $ns->redirect($params['url_retour']);
                }
            }
        }
        // messages
        if (!$success) {
            $this->data['message'] = "Impossible de supprimer cet utilisateur";
        }
    }

    /**
     * oubliAction : affichage du formulaire pour mot de passe oublie
     *
     * @access public
     * @return void
     */
    public function oubliAction($request, $titre = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        if (!empty($_POST)) {
            $login = $ns->strip_tags($request->POST['login']);
            if ($ns->est_email($login)) {
                $user = $this->_crud->getUserByLogin($login);
            } else {
                $this->data['error'] = 'Vous devez fournir l\'adresse e-mail utilisée lors de votre inscription.';
                $user = 0;
            }
            // verifie que l'utilisateur n'est pas suspendu
            if ($user && !$user['active']) {
                $this->data['error'] = 'Ce compte est suspendu.';
                $user = 0;
            }
            // securite
            if ($user) {
                $login = $user['login'];
                $lien_confirmation = __WWW__ . '/users/renew?l=' . base64_encode($login) . '&c=' . hash('sha256', $user['code_confirmation']);
                // envoie un mail pour proposer le changement de mot de passe
                if ($titre === null) {
                    $titre = Clementine::$config['clementine_global']['site_name'] . " : demande de renouvellement de votre mot de passe ";
                }
                $contenu = $this->getBlockHtml('users/mail_oubli_pass', array(
                    'user' => $user,
                    'lien_confirmation' => $lien_confirmation
                ));
                $contenu_texte = $ns->strip_tags(str_replace('<hr />', '------------------------------', str_replace('<br />', "\n", $contenu))) . "\n";
                $to = $login;
                $from = Clementine::$config['clementine_global']['email_exp'];
                $fromname = Clementine::$config['clementine_global']['site_name'];
                $ns->envoie_mail($to, $from, $fromname, $titre, $contenu_texte, $contenu);
            }
            if (empty($this->data['error'])) {
                $this->data['message'] = 'Un e-mail contenant les instructions à suivre pour renouveler votre mot de passe vous a été envoyé. <br /><br /><strong>N\'oubliez pas de consulter également votre courrier indésirable.</strong>';
            }
        }
    }

    /**
     * renewAction : affichage du block confirmant le renouvellement du mot de passe
     *
     * @access public
     * @return void
     */
    public function renewAction($request, $titre = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        $code = $request->get('string', 'c');
        $login = base64_decode($request->get('string', 'l'));
        if ($ns->est_email($login)) {
            $user = $this->_crud->getUserByLogin($login);
            if ($user) {
                $hash_confirmation = hash('sha256', $user['code_confirmation']);
                if ($hash_confirmation == $code) {
                    // renouvelle les identifiants, change le code de confirmation, et envoie un mot de passe a l'utilisateur
                    $newpass = substr(hash('sha256', hash('sha256', (microtime() . rand(0, getrandmax())))), 0, 8);
                    if ($this->_crud->updatePassword($login, $newpass)) {
                        if ($titre === null) {
                            $titre = Clementine::$config['clementine_global']['site_name'] . " : renouvellement de votre mot de passe";
                        }
                        $contenu = $this->getBlockHtml('users/mail_renew_pass', array(
                            'user' => $user,
                            'newpass' => $newpass
                        ));
                        $contenu_texte = $ns->strip_tags(str_replace('<hr />', '------------------------------', str_replace('<br />', "\n", $contenu))) . "\n";
                        $to = $login;
                        $from = Clementine::$config['clementine_global']['email_exp'];
                        $fromname = Clementine::$config['clementine_global']['site_name'];
                        if (!$ns->envoie_mail($to, $from, $fromname, $titre, $contenu_texte, $contenu)) {
                            $this->data['error'] = 'Impossible d\'envoyer le mail de renouvellement du mot de passe';
                        }
                    } else {
                        $this->data['error'] = 'Impossible de renouveler le mot de passe';
                    }
                } else {
                    $this->data['error'] = 'Ce lien est expiré ou invalide. ';
                }
            }
        }
    }

    /**
     * createAction : formulaire de creation d'utilisateur
     *
     * @access public
     * @return void
     */
    public function createAction($request, $params = null)
    {
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        $conf = $this->getModuleConfig();
        // default_group
        $default_group = 'clients';
        if (!empty($params['default_group'])) {
            $default_group = $params['default_group'];
        } else if (!empty($conf['default_group'])) {
            $default_group = $conf['default_group'];
        }
        $params['default_group'] = $default_group;
        $this->data['default_group'] = $default_group;
        if (empty($params['url_retour'])) {
            $params['url_retour'] = __WWW__ . '/users/created?isnew=1&';
            if ($this->_crud->hasPrivilege('manage_users')) {
                $params['url_retour'] = __WWW__ . '/users/index?created';
            }
        }
        $url_retour_parameters = array(
            'id' => $this->_crud->table_users . '.id',
        );
        if (empty($params['url_retour_parameters'])) {
            $params['url_retour_parameters'] = $url_retour_parameters;
        } else {
            $params['url_retour_parameters'] = array_merge($params['url_retour_parameters'], $url_retour_parameters);
        }
        return parent::createAction($request, $params);
    }

    // envoie les mails s'il la création s'est bien passée
    public function handle_errors($request, $errors, $params = null)
    {
        if (!count($errors) && ($this->data['formtype'] == 'create')) {
            $created_user_id = $this->data['values'][0][$this->_crud->table_users . '.id'];
            if (!empty($created_user_id) && $created_user = $this->_crud->getUser($created_user_id)) {
                $sendmail_data = array(
                    'user' => $created_user,
                    'isnew' => array(
                        'password' => $request->post('string', 'mot_de_passe')
                    ),
                );
                $this->sendmail_confirmation($sendmail_data);
                $this->sendmail_notification($sendmail_data);
                $this->sendmail_activation($sendmail_data);
                // tente l'autologin si necessaire
                $auth = $this->_crud->getAuth();
                // pas si on est déjà connecté !
                if ($created_user['active'] && !$auth) {
                    $this->login($created_user['login'], $request->post('string', 'mot_de_passe'));
                }
            }
        }
        return parent::handle_errors($request, $errors, $params);
    }

    /**
     * updateAction : formulaire de modification d'un utilisateur
     *
     * @param mixed $request
     * @param mixed $params
     * @access public
     * @return void
     */
    public function updateAction($request, $params = null)
    {
        $this->_crud->needAuth();
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        $ret = parent::updateAction($request, $params);
        return $ret;
    }

    /**
     * readAction :
     *
     * @param mixed $request
     * @param mixed $params
     * @access public
     * @return void
     */
    public function readAction($request, $params = null)
    {
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        return parent::readAction($request, $params);
    }

    /**
     * deletetmpfileAction :
     *
     * @param mixed $request
     * @param mixed $params
     * @access public
     * @return void
     */
    public function deletetmpfileAction($request, $params = null)
    {
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        return parent::deletetmpfileAction($request, $params);
    }

    public function createdAction($request, $params = null)
    {
        $ns = $this->getModel('fonctions');
        $users = $this->_crud;
        // url_retour par défaut si utilisateur créé par un admin
        if (!isset($params['url_retour']) && $users->hasPrivilege('manage_users')) {
            $params['url_retour'] = __WWW__ . '/users/index';
        }
        if (isset($params['url_retour'])) {
            $ns->redirect($params['url_retour']);
        }
    }

    public function validate($insecure_values, $insecure_primary_key = null, $params = null)
    {
        $previous_errors = parent::validate($insecure_values, $insecure_primary_key, $params);
        $my_errors = array();
        $ns = $this->getModel('fonctions');
        $users = $this->_crud;
        // recuperation des donnees et assainissement
        $donnees = $this->sanitize($insecure_values, $params);
        // la modification du login requiert le privilege manage_users (ou un bypass dans $params)
        if (!empty($insecure_primary_key[$users->table_users . '-id']) && isset($donnees[$users->table_users . '-login'])) {
            $user = $users->getUser((int)$insecure_primary_key[$users->table_users . '-id']);
            if ($user['login'] != $donnees[$users->table_users . '-login']) {
                if (!($users->hasPrivilege('manage_users') || isset($params['allow_login_modification']))) {
                    $ns->redirect($users->getUrlLogin());
                } else {
                    // verifie que l'utilisateur n'existe pas déjà
                    $already_user = $users->getUserByLogin($donnees[$users->table_users . '-login']);
                    if ($already_user) {
                        $my_errors[$users->table_users . '-login'] = "l'utilisateur existe déjà";
                    }
                }
            }
        }
        // verification des donnees requises
        $login = '';
        if (!empty($donnees[$users->table_users . '-login'])) {
            $login = $donnees[$users->table_users . '-login'];
        }
        if (!strlen($login) || !$ns->est_email($login)) {
            $my_errors[$users->table_users . '-login'] = "mauvais format d'adresse email";
        }
        // verifie que l'utilisateur n'existe pas déjà dans le cas d'une création
        if (empty($insecure_primary_key[$users->table_users . '-id']) && isset($donnees[$users->table_users . '-login'])) {
            $already_user = $users->getUserByLogin($login);
            if ($already_user) {
                $my_errors[$users->table_users . '-login'] = "l'utilisateur existe déjà";
            }
        }
        if ($donnees['mot_de_passe'] != $donnees['confirmation_du_mot_de_passe']) {
            $err_msg_pwd = "le mot de passe et sa confirmation ne correspondent pas";
            $my_errors['mot_de_passe'] = $err_msg_pwd;
            $my_errors['confirmation_du_mot_de_passe'] = $err_msg_pwd;
        }
        $ret = $this->getModel('fonctions')->array_replace_recursive($my_errors, $previous_errors);
        return $ret;
    }

    public function sendmail_confirmation($data, $titre = null, $params = null)
    {
        $conf = $this->getModuleConfig();
        $ns = $this->getModel('fonctions');
        if ($conf['send_account_confirmation'] && isset($data['isnew'])) {
            if ($titre === null) {
                $titre = Clementine::$config['clementine_global']['site_name'] . " : votre nouveau compte";
            }
            $contenu = $this->getBlockHtml('users/mail_confirmation', $data);
            $contenu_texte = $ns->strip_tags(str_replace('<hr />', '------------------------------', str_replace('<br />', "\n", $contenu))) . "\n";
            $to = $data['user']['login'];
            $from = Clementine::$config['clementine_global']['email_exp'];
            $fromname = Clementine::$config['clementine_global']['site_name'];
            if ($ns->envoie_mail($to, $from, $fromname, $titre, $contenu_texte, $contenu)) {
                return true;
            }
        }
        return false;
    }

    public function sendmail_notification($data, $titre = null, $params = null)
    {
        $conf = $this->getModuleConfig();
        $ns = $this->getModel('fonctions');
        if ($conf['send_account_notification']) {
            if ($titre === null) {
                $titre = Clementine::$config['clementine_global']['site_name'] . " : inscription d'un nouvel utilisateur";
            }
            $contenu = $this->getBlockHtml('users/mail_notification', $data);
            $contenu_texte = $ns->strip_tags(str_replace('<hr />', '------------------------------', str_replace('<br />', "\n", $contenu))) . "\n";
            $to = Clementine::$config['clementine_global']['email_prod'];
            $from = Clementine::$config['clementine_global']['email_exp'];
            $fromname = Clementine::$config['clementine_global']['site_name'];
            if ($ns->envoie_mail($to, $from, $fromname, $titre, $contenu_texte, $contenu)) {
                return true;
            }
        }
        return false;
    }

    public function sendmail_activation($data, $titre = null, $params = null)
    {
        $conf = $this->getModuleConfig();
        if ($conf['send_account_activation']) {
            // TODO: envoi d'un mail d'activation avec une URL permettant d'activer le compte
        }
    }

    public function hide_fields_create_or_update($request, $params = null)
    {
        $ret = parent::hide_fields_create_or_update($request, $params);
        $users = $this->getModel('users');
        $this->hideFields(array(
            $users->table_users . '.date_creation',
            $users->table_users . '.date_modification',
            $users->table_users . '.password',
            $users->table_users . '.salt',
            $users->table_users . '.code_confirmation',
            $users->table_users . '.active',
        ));
        return $ret;
    }

    public function add_fields_create_or_update($request, $params = null)
    {
        $ret = parent::add_fields_create_or_update($request, $params);
        $this->addField('mot_de_passe', null, null, array(
            'type' => 'password'
        ));
        $this->addField('confirmation_du_mot_de_passe', null, null, array(
            'type' => 'password'
        ));
        return $ret;
    }

    public function override_fields_create_or_update($request, $params = null)
    {
        $ret = parent::override_fields_create_or_update($request, $params);
        $users = $this->_crud;
        if ($this->data['formtype'] == 'create') {
            $this->overrideField($users->table_users . '.login', array(
                'autofocus' => 'autofocus'
            ));
        }
        $this->overrideField($users->table_users . '.login', array(
            'placeholder' => 'user@domain.com'
        ));
        if (($this->data['formtype'] == 'update') && (!$users->hasPrivilege('manage_users'))) {
            $this->overrideField($users->table_users . '.login', array(
                'type' => 'readonly'
            ));
        }
        $this->setMandatoryFields(array(
            $users->table_users . '.login',
            'mot_de_passe',
            'confirmation_du_mot_de_passe',
        ));
        return $ret;
    }

    public function alter_values($request, $params = null)
    {
        $ret = parent::alter_values($request, $params);
        $this->addClass('more_classes_wrap', 'well');
        return $ret;
    }

    public function alter_values_create_or_update($request, $params = null)
    {
        $ret = parent::alter_values_create_or_update($request, $params);
        if (($this->data['formtype'] == 'update')) {
            $params['force_default_value'] = true;
            $this->setDefaultValue('mot_de_passe', 'password', $params);
            $this->setDefaultValue('confirmation_du_mot_de_passe', 'password', $params);
        }
        return $ret;
    }

}
