<?php
/**
 * Hybrid Cluster Roundcube Plugin
 *
 * Provides Email time machine support
 *
 * XXX Because of lack of documentation in RCB this reaches up into RCB
 * internals a bit. May require more careful construction at a later date.
 *
 * @version 1.0
 * @author Rob Haswell
 * @url http://www.hybrid-cluster.com/
 */

class hybrid_cluster extends rcube_plugin {

    public $task = "mail";

    public function init() {
        require_once "{$_SERVER['DOCUMENT_ROOT']}/include/HybridClusterAPI.class.php";
        require_once "{$_SERVER['DOCUMENT_ROOT']}/include/HybridClusterAPIInternalException.class.php";
        require_once "{$_SERVER['DOCUMENT_ROOT']}/include/jsonRPCClient.class.php";
        require_once "{$_SERVER['DOCUMENT_ROOT']}/include/Spyc.class.php";
        require_once "{$_SERVER['DOCUMENT_ROOT']}/include/Site.class.php";
        $this->include_script('client.js');
        $this->add_hook('template_container', Array($this, 'template_container'));
        $this->register_action('plugin.hc_select_snapshot', array($this, 'select_snapshot'));
    }

    private function getUsername() {
        $rcmail = rcmail::get_instance();
        $user = $rcmail->user;
        list($username) = explode("#", $user->data['username']);
        return $username;
    }

    private function getSnapshot() {
        $rcmail = rcmail::get_instance();
        $user = $rcmail->user;
        list($username, $snapshot) = explode("#", $user->data['username']);
        return (string)$snapshot;
    }

    public function template_container($params) {
        if (!isset($params['content']))
            $params['content'] = "";
        ob_start();

        switch ($params['name']) {
            case 'taskbar':
                $rcmail = rcmail::get_instance();
                $username = $this->getUsername();
                $cur_snapshot = $this->getSnapshot();

                $api = HybridClusterAPI::get();
                $snapshots = $api->availableSnapshotsForEmail($username);
                $snapshots = array_reverse($snapshots);

                $input = new html_select(Array("id" => "hc-snapshot"));
                $input->add('Current', '');
                foreach ($snapshots as $snapshot)
                    $input->add(date('jS M \'y &\\n\d\a\s\h; H:i', $snapshot['timestamp']), $snapshot['name']);

                ?>
                <div style="position: absolute; top: 7px; right: 380px">
                <label>Select snapshot:
                    <?=$input->show($cur_snapshot); ?>
                </label>
                </div>
                <?php
                break;
        }
        return Array("content" => $params['content'] . ob_get_clean());
    }

    public function select_snapshot($args) {
        $rcmail = rcmail::get_instance();
        $username = $this->getUsername();

        $snapshot = $_POST['snapshot'];

        $db = $rcmail->get_dbh();

        $res = $db->query("select password from mailboxes where username=?", $username);
        $row = $db->fetch_assoc($res);

        $userdata = array('user' => $_SESSION['username'], 'host' => $_SESSION['imap_host'], 'lang' => $RCMAIL->user->language);
        $rcmail->logout_actions();
        $rcmail->kill_session();
        $rcmail->plugins->exec_hook('logout_after', $userdata);

        //session_destroy();

        $rcmail->session->remove('temp');
        $rcmail->session->regenerate_id(false);
        $rcmail->session_init();

        $new_username = $snapshot ? "{$username}#{$snapshot}" : $username;
        $rcmail->login($new_username, $row['password']);

        // send auth cookie if necessary
        $rcmail->session->set_auth_cookie();

        $rcmail->output->command('plugin.hc_select_snapshot_callback', array('message' => 'Switching to snapshot'));
    }
}

