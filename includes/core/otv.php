<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      otv.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2021 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


if (file_exists('../sources/SecureHandler.php')) {
    include_once '../sources/SecureHandler.php';
} elseif (file_exists('./sources/SecureHandler.php')) {
    include_once './sources/SecureHandler.php';
} else {
    throw new Exception("Error file '/sources/SecureHandler.php' not exists", 1);
}
if (isset($_SESSION) === false) {
    session_name('teampass_session');
    session_start();
}
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}
?>
<body class="hold-transition otv-page">
    <div class="login-box" style="margin-top:10%; width:90%; opacity:0.9;">
        
        <!-- /.login-logo -->
        <div class="card">
        <div class="login-logo">
            <a href="../../index.php"><b><?php echo TP_TOOL_NAME; ?></b></a>
        </div>
            <div class="card-body login-card-body">
<?php
if (filter_var($_GET['code'], FILTER_SANITIZE_STRING) !== false
    && filter_var($_GET['stamp'], FILTER_VALIDATE_INT) !== false
    && filter_var($_GET['key'], FILTER_SANITIZE_STRING) !== false
) {
    //Include files
    include_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    include_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
    include_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

    // Open MYSQL database connection
    include_once './includes/libraries/Database/Meekrodb/db.class.php';
    if (defined('DB_PASSWD_CLEAR') === false) {
        define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
    }
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = DB_PASSWD_CLEAR;
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    if (isset($SETTINGS['otv_is_enabled']) === false
        || (int) $SETTINGS['otv_is_enabled'] === 0
    ) {
        echo '
        <div class="text-center text-danger">
        <h3><i class="fas fa-exclamation-triangle mr-2"></i>One-Time-View is not allowed!</h3>
        </div>';

        exit(true);
    }

    // Prepare GET variables
    $code = filter_var($_GET['code'], FILTER_SANITIZE_STRING);
    $key = filter_var($_GET['key'], FILTER_SANITIZE_STRING);
    $stamp = filter_var($_GET['stamp'], FILTER_VALIDATE_INT);

    // check session validity
    $data = DB::queryfirstrow(
        'SELECT id, timestamp, code, item_id, encrypted
        FROM '.prefixTable('otv').'
        WHERE code = %s',
        $code
    );
    if ((int) $data['timestamp'] === (int) $stamp) {
        // otv is too old
        if ($data['timestamp'] < (time() - ($SETTINGS['otv_expiration_period'] * 86400))) {
            $html = 'Link is too old!';
        } else {
            // get from DB
            $dataItem = DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('items').' as i
                INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
                WHERE i.id = %i AND l.action = %s',
                $data['item_id'],
                'at_creation'
            );

            // is Item still valid regarding number of times being seen
            // Decrement the number before being deleted
            $dataDelete = DB::queryfirstrow(
                'SELECT * FROM '.prefixTable('automatic_del').' WHERE item_id=%i',
                $data['item_id']
            );
            if (isset($SETTINGS['enable_delete_after_consultation']) && $SETTINGS['enable_delete_after_consultation'] == 1) {
                if ((int) $dataDelete['del_enabled'] === 1) {
                    if ((int) $dataDelete['del_type'] === 1 && (int) $dataDelete['del_value'] >= 1) {
                        // decrease counter
                        DB::update(
                            $pre.'automatic_del',
                            array(
                                'del_value' => $dataDelete['del_value'] - 1,
                                ),
                            'item_id = %i',
                            $data['item_id']
                        );
                    } elseif (((int) $dataDelete['del_type'] === 1 && (int) $dataDelete['del_value'] <= 1)
                        || ((int) $dataDelete['del_type'] === 2 && (int) $dataDelete['del_value'] < time())
                    ) {
                        // delete item
                        DB::delete($pre.'automatic_del', 'item_id = %i', $data['item_id']);
                        // make inactive object
                        DB::update(
                            prefixTable('items'),
                            array(
                                'inactif' => '1',
                                ),
                            'id = %i',
                            $data['item_id']
                        );
                        // log
                        logItems(
                            $SETTIGNS,
                            $data['item_id'],
                            $dataItem['label'],
                            (int) OTV_USER_ID,
                            'at_delete',
                            'otv',
                            'at_automatically_deleted'
                        );

                        echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fa fa-warning fa-2x"></i>&nbsp;'.
                        addslashes($LANG['not_allowed_to_see_pw_is_expired']).'</div>';

                        return false;
                    }
                }
            }

            // Uncrypt PW
            $password_decrypted = cryption(
                $data['encrypted'],
                $key,
                'decrypt',
                $SETTINGS
            );

            // get data
            $label = strip_tags($dataItem['label']);
            $email = $dataItem['email'];
            $url = $dataItem['url'];
            $description = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags($dataItem['description'], TP_ALLOWED_TAGS));
            $login = str_replace('"', '&quot;', $dataItem['login']);

            // display data
            $html = '<div class="text-center">'.
                '<h3>One-Time item view page</h3>'.
                "<p class='font-weight-light mt-3'>- Here are the details of the Item that has been shared to you -</p>".
                "<div class='mt-5'>".
                '<table class="table text-left" style="margin: 0 auto;">'.
                '<tr><th>Label:</th><td>'.$label.'</td></tr>'.
                '<tr><th>Password:</th><td>'.addslashes($password_decrypted['string']).'</td></tr>'.
                '<tr><th>Description:</th><td>'.$description.'</td></tr>'.
                '<tr><th>login:</th><td>'.$login.'</td></tr>'.
                '<tr><th>URL:</th><td>'.$url.'</td></tr>'.
                '</table></div>'.
                "<p class='mt-3 text-info'><i class=\"fas fa-info mr-2\"></i>Copy carefully the data you need. This page is only visible once.</div>".
                '</div>';

            // log
            logItems(
                $SETTINGS,
                $data['item_id'],
                $dataItem['label'],
                (int) OTV_USER_ID,
                'at_shown',
                'otv'
            );

            // delete entry
            DB::delete(prefixTable('otv'), 'id = %i', $data['id']);

            // display
            echo $html;
        }
    } else {
        echo '<div class="text-center text-danger">
        <h3><i class="fas fa-exclamation-triangle mr-2"></i>Not a valid page!</h3>
        </div>';
    }
} else {
    echo '
    <div class="text-center text-danger">
    <h3><i class="fas fa-exclamation-triangle mr-2"></i>No valid OTV parameters!</h3>
    </div>';
}
?>
            </div>
        </div>
    </div>
</body>
