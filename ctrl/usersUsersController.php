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
     * loginAction : login de l'utilisateur si POST et redirige l'utilisateur vers la page appelante si OK. Affiche le formulaire de login sinon (si pas ok, ou si pas de POST).
     * 
     * @access public
     * @return void
     */
    function loginAction ()
    {
        $ns = $this->getModel('fonctions');
        $this->data['message'] = "Connexion requise";
        // Traitement de la demande de login
        if (!empty($_POST)) {
            // collect the data from the user
            $login    = $ns->strip_tags($ns->ifPost('string', 'login'));
            $password = $ns->strip_tags($ns->ifPost('string', 'password'));
            if (empty($login)) {
                $this->data['message'] = 'Vous devez fournir vos identifiants pour accéder à cette page';
            } else {
                // tente l'authentification
                $auth = $this->getModel('users')->tryAuth($login, $password);
                // recuperation de l'url retour
                $url_retour = isset($_POST['url_retour']) ? $_POST['url_retour'] : __WWW__;
                if (!session_id()) {
                    session_start();
                }
                if ($auth) {
                    $_SESSION['auth'] = $auth;
                    $ns->redirect($url_retour);
                } else {
                    // failure: clear session
                    if (isset($_SESSION['auth'])) {
                        unset($_SESSION['auth']);
                    }
                    // securite contre le bruteforce, ca mange pas de pain
                    $this->data['message'] = 'Echec de l\'identification.';
                }
            }
        }
        // render de la vue
        $this->data['url_retour'] = $ns->ifSet('url_retour', 'get') ? $ns->ifGet('string', 'url_retour') : __WWW__;
    }

    /**
     * logoutAction : logout de l'utilisateur
     * 
     * @access public
     * @return void
     */
    function logoutAction ()
    {
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['auth'])) {
            unset($_SESSION['auth']);
        }
        session_unset();
    }

    /**
     * indexAction : liste des utilisateurs
     * 
     * @access public
     * @return void
     */
    function indexAction ()
    {
        $this->getModel('users')->needAuth();
        $this->data['users'] = $this->getModel('users')->getUsers();
    }

    /**
     * editAction : affiche le block d'edition d'un utilisateur
     * 
     * @access public
     * @return void
     */
    function editAction ()
    {
        $this->getModel('users')->needAuth();
        $ns = $this->getModel('fonctions');
        $this->data['id'] = $ns->ifGet('int', 'id');
        $user = $this->getModel('users')->getUser($this->data['id']);
        $user['password'] = 'password';
        $this->data['user'] = $user;
    }

    /**
     * addAction : affiche le block de creation d'un utilisateur
     * 
     * @access public
     * @return void
     */
    function addAction ()
    {
        $this->getModel('users')->needAuth();
        $ns = $this->getModel('fonctions');
        $this->data['id'] = $ns->ifGet('int', 'id');
        $this->data['user'] = $this->getModel('users')->getUser($this->data['id']);
    }

    /**
     * deleteAction : prepare les variables pour la suppression d'un user
     * 
     * @access public
     * @return void
     */
    function deleteAction ()
    {
        $this->getModel('users')->needAuth();
        $auth = $this->getModel('users')->getAuth();
        $ns = $this->getModel('fonctions');
        $id = $ns->ifGet('int', 'id');
        $success = false;
        if ($id != $auth['id']) {
            // suppression du user
            if ($id) {
                $success = $this->getModel('users')->delUser($id);
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
    function oubliAction ()
    {
        $ns = $this->getModel('fonctions');
        if (!empty($_POST)) {
            $login = $ns->ifPost('string', 'login');
            if ($ns->est_email($login)) {
                $user = $this->getModel('users')->getUserByLogin($login);
            } else {
                sleep(3);
                $this->data['error'] = 'Vous devez fournir l\'adresse email utilisée lors de votre inscription.';
                $user = 0;
            }
            // securite
            if ($user) {
                $login = $user['login'];
                $lien_confirmation = __WWW__ . '/users/renew?l=' . base64_encode($login) . '&c=' . hash('sha256', $user['code_confirmation']);
                // envoie un mail pour proposer le changement de mot de passe
                // version html
                $titre      = Clementine::$config['clementine_global']['site_name'] . " : demande de renouvellement de votre mot de passe ";
                $html_body  = "
                    Bonjour, <br />
                    <br />
                    Une demande de renouvellement de mot de passe a été reçue pour votre compte sur <a href='" . __WWW__ . "'>" . Clementine::$config['clementine_global']['site_name'] . "</a> <br />
                    <br />
                    Si vous êtes bien à l'origine de cette demande, <a href='" . $lien_confirmation . "'>cliquez ici pour confirmer cette demande</a> ou copiez-collez le lien suivant dans votre navigateur : <br />$lien_confirmation<br />
                    <br />
                    Bonne journée,<br />
                    <br />
                    <hr />
                    Note : cet email a été envoyé automatiquement suite à une demande reçue sur notre site. Merci de ne pas y répondre. ";
                // version texte
                $text_body  = $ns->strip_tags(str_replace('<hr />', '------------------------------',
                                              str_replace('<br />', "\n", $html_body))) . "\n";
                $mail_parti = $ns->envoie_mail($login, Clementine::$config['clementine_global']['email_prod'], Clementine::$config['clementine_global']['site_name'], $titre, $text_body, $html_body);
                // temporisation lors de l'envoi des mails
                sleep(3);
                if ($mail_parti) {
                    $this->data['message'] = 'Un email contenant les instructions à suivre pour renouveler votre mot de passe vous a été envoyé. ';
                }
            }
        }
    }

    /**
     * renewAction : affichage de le block confirmant le renouvellement du mot de passe
     * 
     * @access public
     * @return void
     */
    function renewAction ()
    {
        $ns = $this->getModel('fonctions');
        $code = $ns->ifGet('string', 'c');
        $login = base64_decode($ns->ifGet('string', 'l'));
        if ($ns->est_email($login)) {
            $user = $this->getModel('users')->getUserByLogin($login);
            if ($user) {
                $hash_confirmation = hash('sha256', $user['code_confirmation']);
                if ($hash_confirmation == $code) {
                    sleep(3);
                    // renouvelle les identifiants, change le code de confirmation, et envoie un mot de passe a l'utilisateur
                    $newpass = substr(hash('sha256', hash('sha256', (microtime() . rand(0, getrandmax())))), 0, 8);
                    if ($this->getModel('users')->updatePassword($login, $newpass)) {
                        $titre      = Clementine::$config['clementine_global']['site_name'] . " : renouvellement de votre mot de passe";
                        $html_body  = "
                            Bonjour, <br />
                            <br />
                            Une demande de renouvellement de mot de passe a été reçue pour votre compte sur <a href='" . __WWW__ . "'>" . Clementine::$config['clementine_global']['site_name'] . "</a> <br />
                            <br />
                            Cette demande ayant été confirmée, votre mot de passe a été renouvellé. Votre nouveau mot de passe est : " . $newpass . "
                            <br />
                            Bonne journée,<br />
                            <br />
                            <hr />
                            Note : cet email a été envoyé automatiquement suite à une demande reçue sur notre site. Merci de ne pas y répondre. ";
                        // version texte
                        $text_body  = $ns->strip_tags(str_replace('<hr />', '------------------------------',
                                                      str_replace('<br />', "\n", $html_body))) . "\n";
                        $mail_parti = $ns->envoie_mail($login, Clementine::$config['clementine_global']['email_prod'], Clementine::$config['clementine_global']['site_name'], $titre, $text_body, $html_body);
                        if (!$mail_parti) {
                            $this->data['error'] = 'Impossible d\'envoyer le mail de renouvellement du mot de passe';
                        }
                    } else {
                        $this->data['error'] = 'Impossible de renouveler le mot de passe';
                    }
                } else {
                    sleep(3);
                    $this->data['error'] = 'Code invalide';
                }
            }
        }
    }

    /**
     * createAction : affiche le formulaire de creation d'utilisateur
     * 
     * @access public
     * @return void
     */
    function createAction ()
    {
        $this->getModel('users')->needAuth();
    }

    /**
     * validuserAction : verifie les donnees postees et ajoute ou modifie un user
     * 
     * @access public
     * @return void
     */
    function validuserAction ()
    {
        //$this->getModel('users')->needAuth();
        $ns = $this->getModel('fonctions');
        // recupere les parametres
        $id = $ns->ifPost('int', 'id');
        $donnees = array ();
        $donnees['date_modification']       = date('Y-m-d H:i:s');
        if (isset($_POST['password']) && ($_POST['password'] != 'password')) {
            $donnees['password']            = $ns->ifPost('string', 'password', null, null, 0, 0, 0);
        } else {
            $donnees['password'] = '';
        }
        if (isset($_POST['password_conf']) && ($_POST['password_conf'] != 'password')) {
            $donnees['password_conf']       = $ns->ifPost('string', 'password_conf', null, null, 0, 0, 0);
        } else {
            $donnees['password_conf'] = '';
        }
        if (isset($_POST['login'])) {
            $donnees['login']            = $ns->ifPost('string', 'login', null, null, 0, 0, 0);
        }
        // verification des donnees requises
        $erreurs = array();
        if (!strlen($donnees['login']) || !$ns->est_email($donnees['login'])) {
            $erreurs[] = 'mail';
        }
        if ((!$id && !strlen($donnees['password'])) || ($donnees['password'] != $donnees['password_conf'])) {
            $erreurs[] = 'password';
        }
        if (count($erreurs)) {
            $this->data['message'] = 'Une erreur s\'est produite : ' . implode("\n", $erreurs);
        } else {
            $new = 0;
            if (!$id) {
                $id = $this->getModel('users')->addUser($donnees['login']);
                $new = $id;
            }
            $olduser = '';
            if ($id) {
                $olduser = $this->getModel('users')->modUser($id, $donnees);
            }
            if (!($id && $olduser)) {
                $this->data['message'] = 'Creation ou modification impossible';
            }
        }
    }

}
?>
