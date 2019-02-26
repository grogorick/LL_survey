<?php
/*
Plugin Name:  LL_survey
Plugin URI:   https://linda-liest.de/
Description:  Survey
Version:      1.0
Author:       Steve Grogorick
Author URI:   https://grogorick.de/
License:      GPLv3
License URI:  http://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) {
  echo '<html lang="de"><body><span style="font-size: 100vh; font-family: monospace; color: #eee; position: absolute; bottom: 0;">404</span></body></html>';
  http_response_code(404);
  exit;
}



class LL_survey
{
  const _ = 'LL_survey';

  const option_msg                          = self::_ . '_msg';
  const option_test                         = self::_ . '_test';

  const table_surveys                       = self::_ . '_surveys';
  const table_questions                     = self::_ . '_questions';
  const table_answers                       = self::_ . '_answers';

  const admin_page_settings                 = self::_ . '_settings';
  const admin_page_surveys                  = self::_ . '_surveys';
  const admin_page_survey_edit              = self::_ . '_surveys&edit=';

  const shortcode_TEST                      = ['code'    => 'LL_survey_TEST',
                                               'html'    => '[shortcode_TEST attr=""]'];

  const list_item = '<span style="padding: 5px;">&ndash;</span>';
  const arrow_up = '&#x2934;';
  const arrow_down = '&#x2935;';
  const secondary_settings_label = 'style="vertical-align: baseline;"';



	static function _($member_function) { return [self::_, $member_function]; }

  static function pluginPath() { return plugin_dir_path(__FILE__); }
  static function admin_url() { return get_admin_url() . 'admin.php?page='; }
  static function json_url() { return get_rest_url() . self::_ . '/v1/'; }

  static function get_option_array($option) {
    $val = get_option($option);
    if (empty($val))
      return [];
    return $val;
  }

  static function message($msg, $sticky_id = false)
  {
    $msgs = self::get_option_array(self::option_msg);
    $msgs[] = [$msg, $sticky_id];
    update_option(self::option_msg, $msgs);
  }

  static function hide_message($sticky_id)
  {
    $msgs = self::get_option_array(self::option_msg);
    foreach ($msgs as $key => &$msg) {
      if ($msg[1] === $sticky_id) {
        unset($msgs[$key]);
      }
    }
    if (empty($msgs)) {
      delete_option(self::option_msg);
    }
    else {
      update_option(self::option_msg, $msgs);
    }
  }

  static function admin_notices()
  {
    // notice
    // notice-error, notice-warning, notice-success or notice-info
    // is-dismissible
    $msgs = self::get_option_array(self::option_msg);
    if (!empty($msgs)) {
      foreach ($msgs as $key => &$msg) {
        $hide_class = ($msg[1]) ? ' ' . self::_ . '_sticky_message' : '';
        echo '<div class="notice notice-info' . $hide_class . '">';
        if ($msg[1]) {
          echo '<p style="float: right; padding-left: 20px;">' .
                '(<a class="' . self::_ . '_sticky_message_hide_link" href="' . self::json_url() . 'get?hide_message=' . urlencode($msg[1]) . '">' . __('Ausblenden', 'LL_survey') . '</a>)' .
               '</p>';
        }
        echo '<p>' . nl2br($msg[0]) . '</p></div>';
        if (!$msg[1]) {
          unset($msgs[$key]);
        }
      }
?>
      <script>
        new function() {
          var msg_tags = document.querySelectorAll('.<?=self::_?>_sticky_message');
          for (var i = 0; i < msg_tags.length; i++) {
            var msg_tag = msg_tags[i];
            var a_tag = msg_tag.querySelector('.<?=self::_?>_sticky_message_hide_link');
            jQuery(a_tag).click(function(e) {
              e.preventDefault();
              this.parentNode.parentNode.style.display = 'none';
              jQuery.get(this.href + '&no_redirect');
            });
          }
        };
      </script>
<?php
      if (empty($msgs)) {
        delete_option(self::option_msg);
      }
      else {
        update_option(self::option_msg, $msgs);
      }
    }
  }



  static function array_zip($glue_key_value, $array, $glue_rows = null, $prefix_if_not_empty = '', $suffix_if_not_empty = '')
  {
    if (empty($array)) {
      return is_null($glue_rows) ? [] : '';
    }
    if (is_null($glue_rows)) {
      array_walk($array, function(&$val, $key) 
                                use ($glue_key_value, $suffix_if_not_empty, $prefix_if_not_empty) 
                                { $val = $prefix_if_not_empty . $key . $glue_key_value . $val . $suffix_if_not_empty; });
      return $array;
    }
    else {
      array_walk($array, function(&$val, $key) use ($glue_key_value) { $val = $key . $glue_key_value . $val; });
      return $prefix_if_not_empty . implode($glue_rows, $array) . $suffix_if_not_empty;
    }
  }

  static function escape_key($key)
  {
    if (is_array($key) && count($key) == 1 && isset($key[0]))
      return $key[0];
    return '`' . $key . '`';
  }

  static function escape_keys($keys)
  {
    if (is_array($keys)) {
      return array_map(function($key) {
        return self::escape_key($key);
      }, $keys);
    }
    return self::escape_key($keys);
  }

  static function escape_value($value)
  {
    return '"' . $value . '"';
  }

  static function escape_values($values)
  {
    if (is_array($values)) {
      return array_map(function($val) {
        return (!is_null($val)) ? self::escape_value($val) : 'NULL';
      }, $values);
    }
    return self::escape_value($values);
  }

  static function escape($assoc_array, $escape_keys_only = false)
  {
    $ret = [];
    foreach ($assoc_array as $key => $val) {
      if ($escape_keys_only)
        $value = $val;
      else if (is_null($val))
        $value = 'NULL';
      else if (is_array($val) && count($val) == 1 && isset($val[0]))
        $value = $val[0];
      else
        $value = self::escape_value($val);
      $ret[self::escape_key($key)] = $value;
    }
    return $ret;
  }

  static function build_where($where)
  {
    if (empty($where)) {
      return '';
    }
    $ret = [];
    foreach ($where as $key => &$value) {
      if (is_array($value)) {
        if (isset($value[1])) {
          $ret[] = self::escape_key($key) . ' ' . $value[0] . ' ' . self::escape_value($value[1]);
        }
        else {
          $ret[] = self::escape_key($key) . ' ' . $value[0];
        }
      }
      else {
        $ret[] = self::escape_key($key) . ' = ' . self::escape_value($value);
      }
    }
    return ' WHERE ' . implode(' AND ', $ret);
  }

  static function build_orderby($orderby)
  {
    if (empty($orderby)) {
      return '';
    }
    return self::array_zip(' ', self::escape($orderby, true), ', ', ' ORDER BY ');
  }

  static function _db_build_select($table, $what, $where, $orderby)
  {
    $what = self::escape_keys($what);
    if (is_array($what)) {
      $what = implode(', ', $what);
    }
    $sql = 'SELECT ' . $what . ' FROM ' . self::escape_keys($table) . self::build_where($where) . self::build_orderby($orderby) . ';';
    // self::message($sql);
    return $sql;
  }

  static function _db_insert($table, $data, $timestamp_key = null)
  {
    $data = self::escape($data);
    if (!is_null($timestamp_key))
      $data[self::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'INSERT INTO ' . self::escape_key($wpdb->prefix . $table) . ' ( ' . implode(', ', array_keys($data)) . ' ) VALUES ( ' . implode(', ', array_values($data)) . ' );';
    // self::message($sql);
    if ($wpdb->query($sql))
      return $wpdb->insert_id;
    else
      return false;
  }

  static function _db_update($table, $data, $where, $timestamp_key = null)
  {
    $data = self::escape($data);
    if (!is_null($timestamp_key))
      $data[self::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'UPDATE ' . self::escape_key($wpdb->prefix . $table) . ' SET ' . self::array_zip(' = ', $data, ', ') . self::build_where($where) . ';';
    // self::message($sql);
    return $wpdb->query($sql);
  }

  static function _db_delete($table, $where)
  {
    global $wpdb;
    $sql = 'DELETE FROM ' . self::escape_key($wpdb->prefix . $table) . self::build_where($where) . ';';
    // self::message($sql);
    return $wpdb->query($sql);
  }

  static function _db_select($table, $what = [['*']], $where = [], $orderby = [])
  {
    global $wpdb;
    return $wpdb->get_results(self::_db_build_select($wpdb->prefix . $table, $what, $where, $orderby), ARRAY_A);
  }

  static function _db_select_row($table, $what = [['*']], $where = [])
  {
    global $wpdb;
    return $wpdb->get_row(self::_db_build_select($wpdb->prefix . $table, $what, $where, []), ARRAY_A);
  }



  // surveys
  // - id
  // - name
  // - preview
  // - start
  // - end
  static function db_add_survey($name) { return self::_db_insert(self::table_surveys, ['name' => $name]); }
  static function db_update_survey($survey_id, $data) { return self::_db_update(self::table_surveys, $data, ['id' => $survey_id]); }
  static function db_get_surveys($what = [['*']]) { return self::_db_select(self::table_surveys, $what); }
  static function db_get_survey_by_id($survey_id) { return self::_db_select_row(self::table_surveys, ['id', 'name', 'preview', ['`start` AS s'], ['`end` AS e'], ['DATE_FORMAT(start, "%Y-%m-%dT%H:%i") AS start'], ['DATE_FORMAT(end, "%Y-%m-%dT%H:%i") AS end']], ['id' => $survey_id]); }
  static function db_delete_survey($survey_id) { return self::_db_delete(self::table_surveys, ['id' => $survey_id]); }

  // questions
  // - id
  // - survey
  // - text
  // - type
  // - position
  static function db_add_question($survey_id, $text, $type) { return self::_db_insert(self::table_questions, ['survey' => $survey_id, 'text' => $text, 'type' => $type, 'position' => ['(SELECT IFNULL(MAX(`position`), 0) + 1 FROM `' . self::table_questions . '` WHERE `survey` = "1")']]); }
  static function db_update_question($survey_id, $text, $type) { return self::_db_update(self::table_questions, ['text' => $text, 'type' => $type], ['survey' => $survey_id]); }
  static function db_get_questions_by_survey($survey_id) { return self::_db_select(self::table_questions, [['*']], ['survey' => $survey_id], ['position' => 'ASC']); }



  static function activate()
  {
    global $wpdb;
    $r = [];

    $r[] = self::table_surveys . ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_surveys) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` varchar(200) NOT NULL,
        `preview` tinyint(1) NOT NULL DEFAULT \'1\',
        `start` datetime DEFAULT NULL,
        `end` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    $r[] = self::table_questions . ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_questions) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `survey` int(10) UNSIGNED NOT NULL,
        `text` text NOT NULL,
        `type` varchar(20) NOT NULL,
        `position` int(10) UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`survey`) REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_surveys) . ' (`id`) ON DELETE CASCADE ON UPDATE CASCADE
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    $r[] = self::table_answers . ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_answers) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `question` int(10) UNSIGNED NOT NULL,
        `text` text NOT NULL,
        `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`question`) REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_questions) . ' (`id`) ON DELETE CASCADE ON UPDATE CASCADE
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);
    
    self::message('Datenbank eingerichtet.<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');


//    add_option(self::option_test, 'test');

    self::message('Optionen initialisiert.');


//    register_uninstall_hook(__FILE__, self::_('uninstall'));
  }

  static function uninstall()
  {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_answers) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_questions) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_surveys) . ';');

    delete_option(self::option_msg);
    delete_option(self::option_test);
  }



  static function json_get($request)
  {
    if (isset($request['test'])) {
      return 'test';
    }
  }



  static function hook_admin_menu()
  {
    add_action('admin_menu', self::_('admin_menu'));

    add_action('admin_post_' . self::_ . '_survey_action', self::_('admin_page_survey_action'));
  }

  static function admin_menu()
  {
    $required_capability = 'administrator';
    add_menu_page(self::_, self::_, $required_capability, self::admin_page_settings, self::_('admin_page_settings'), plugins_url('/icon.png', __FILE__));
    add_action('admin_init', self::_('admin_page_settings_general_action'));

    add_submenu_page(self::admin_page_settings,     self::_, 'Einstellungen', $required_capability, self::admin_page_settings,        self::_('admin_page_settings'));
    add_submenu_page(self::admin_page_settings,     self::_, 'Umfragen',      $required_capability, self::admin_page_surveys,         self::_('admin_page_surveys'));
  }



  static function admin_page_settings()
  {
?>
    <div class="wrap">
      <h1><?=__('Allgemeine Einstellungen', 'LL_survey')?></h1>

      <form method="post" action="options.php">
        <?php settings_fields(self::_ . '_general'); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Test', 'LL_survey')?></th>
            <td>
              <input type="text" name="<?=self::option_test?>" value="<?=esc_attr(get_option(self::option_test))?>" placeholder="Test" class="regular-text" />
              &nbsp; <span id="<?=self::option_test?>_response"></span>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
<?php
  }

  static function admin_page_settings_general_action()
  {
    // Save changed settings via WordPress
    register_setting(self::_ . '_general', self::option_test);
  }



  static function admin_page_surveys()
  {
    $sub_page = 'list';
    if (isset($_GET['edit'])) $sub_page = 'edit';
?>
    <div class="wrap">
<?php
    switch ($sub_page) {
      case 'list':
      {
?>
      <h1><?=__('Neue Umfrage', 'LL_survey')?></h1>

      <form method="post" action="admin-post.php">
        <input type="hidden" name="action" value="<?=self::_?>_survey_action" />
        <?php wp_nonce_field(self::_ . '_survey_add'); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Umfragetitel', 'LL_survey')?></th>
            <td>
              <input type="text" name="survey_name" placeholder="<?=__('Meine Umfrage', 'LL_survey')?>" class="regular-text" /> &nbsp;
              <?php submit_button(__('Neue Umfrage anlegen', 'LL_survey'), 'primary', '', false); ?>
            </td>
          </tr>
        </table>
      </form>

      <hr />

      <h1><?=__('Umfragen', 'LL_survey')?></h1>

      <p>
        <?php
        $surveys = self::db_get_surveys();
        $edit_url = self::admin_url() . self::admin_page_survey_edit;
        foreach ($surveys as &$survey) {
?>
          <?=self::list_item?> <a href="<?=$edit_url . $survey['id']?>"><b><?=$survey['name']?></b></a><br />
          <?php
        }
?>
      </p>
<?php
      } break;

      case 'edit':
      {
        $survey_id = $_GET['edit'];
        $survey = self::db_get_survey_by_id($survey_id);
        if (empty($survey)) {
          self::message(sprintf(__('Umfrage <b>%d</b> existiert nicht.', 'LL_survey'), $survey_id));
          wp_redirect(self::admin_url() . self::admin_page_surveys);
          exit;
        }
?>
      <h1><?=__('Umfragen', 'LL_survey')?> &gt; <?=$survey['name']?></h1>

      <form method="post" action="admin-post.php">
        <input type="hidden" name="action" value="<?=self::_?>_survey_action" />
        <?php wp_nonce_field(self::_ . '_survey_edit'); ?>
        <input type="hidden" name="survey_id" value="<?=$survey_id?>" />
        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Umfragetitel', 'LL_survey')?></th>
            <td>
              <input type="text" name="name" value="<?=$survey['name']?>" style="width: 100%;" />
            </td>
          </tr>
          <tr>
            <th scope="row"><?=__('Zeitraum', 'LL_survey')?></th>
            <td>
              <input type="datetime-local" name="start" value="<?=$survey['start']?>" />
              &nbsp;&mdash;&nbsp;
              <input type="datetime-local" name="end" value="<?=$survey['end']?>" />
            </td>
          </tr>
          <tr>
            <th scope="row"><?=__('Vorschaumodus', 'LL_survey')?></th>
            <td>
              <input type="checkbox" name="preview" <?=$survey['preview'] ? 'checked' : ''?> />
              &nbsp; <span class="description"><?=__('Sichtbar nur für eingeloggte Nutzer', 'LL_survey')?></span>
            </td>
          </tr>
          <tr>
            <th scope="row"><?=__('Fragen', 'LL_survey')?></th>
          </tr>
<?php
        $questions = self::db_get_questions_by_survey($survey_id);
        $i = 0;
        foreach ($questions as $question) {
          $t = question['type'];
?>
          <tr>
            <td <?=self::secondary_settings_label?>><p><?=$question['position']?></p></td>
            <td>
              <input type="hidden" name="q_id_<?=$i?>" value="<?=$question['id']?>" />
              <input type="text" name="q_text_<?=$i?>" value="<?=$question['text']?>" style="width: 100%;" /><br />
              <select name="q_type_<?=$i?>">
                <option value="text" <?=$t == 'text' ? 'selected' : ''?>><?=__('Text', 'LL_survey')?></option>
                <option value="check" <?=$t == 'check' ? 'selected' : ''?>><?=__('Check', 'LL_survey')?></option>
              </select>
            </td>
          </tr>
<?php
          ++$i;
        }
?>
          <tr>
            <td <?=self::secondary_settings_label?>><p>+</p></td>
            <td>
              <input type="text" name="q_add_text_0" value="" placeholder="<?=__('Neue Frage')?>" style="width: 100%;" /><br />
              <select name="q_add_type_0">
                <option value="text"><?=__('Text', 'LL_survey')?></option>
                <option value="check"><?=__('Check', 'LL_survey')?></option>
              </select>
            </td>
          </tr>
          <tr>
            <td style="vertical-align: top;"><?php submit_button(__('Umfrage speichern', 'LL_survey'), 'primary', '', false); ?></td>
          </tr>
        </table>
      </form>
      <script>
        new function() {

        };
      </script>

      <hr />

      <h1><?=__('Löschen', 'LL_survey')?></h1>

      <form method="post" action="admin-post.php">
        <input type="hidden" name="action" value="<?=self::_?>_survey_action" />
        <?php wp_nonce_field(self::_ . '_survey_delete'); ?>
        <input type="hidden" name="survey_id" value="<?=$survey_id?>" />
        <?php submit_button(__('Umfrage löschen', 'LL_survey'), ''); ?>
      </form>
<?php
      } break;
    }
?>
    </div>
<?php
  }

  static function admin_page_survey_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_survey_add')) {
        if (!empty($_POST['survey_name'])) {
          $survey_id = self::db_add_survey(trim($_POST['survey_name']));
          self::message(__('Neue Umfrage angelegt.', 'LL_survey'));
          wp_redirect(self::admin_url() . self::admin_page_survey_edit . $survey_id);
          exit;
        }
      }

      else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_survey_edit')) {
        $survey_id = $_POST['survey_id'];
        self::db_update_survey($survey_id, [
          'name' => $_POST['name'] ?? 0,
          'preview' => $_POST['preview'] ? 1 : 0,
          'start' => $_POST['start'] ?: null,
          'end' => $_POST['end'] ?: null]);
        self::message(__('Umfrage gespeichert.', 'LL_survey'));

        $i = 0;
        while (isset($_POST['q_text_' . $i])) {
          self::db_update_question($_POST['q_id_' . $i], $_POST['q_text_' . $i], $_POST['q_type_' . $i]);
          self::message($i . ': ' . print_r($_POST['q_text_' . $i], true));
          ++$i;
        }
        if ($i > 0) {
          self::message(sprintf(__('%d Frage(n) aktualisiert'), $i));
        }

        $i = 0;
        while (isset($_POST['q_add_text_' . $i]) && !empty(trim($_POST['q_add_text_' . $i]))) {
          self::db_add_question($survey_id, trim($_POST['q_add_text_' . $i]), $_POST['q_add_type_' . $i]);
          self::message($i . 'a: ' . print_r($_POST['q_add_text_' . $i], true));
          ++$i;
        }
        if ($i > 0) {
          self::message(sprintf(__('%d Frage(n) hinzugefügt'), $i));
        }

        wp_redirect(self::admin_url() . self::admin_page_survey_edit . $survey_id);
        exit;
      }

      else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_survey_delete')) {
        self::db_delete_survey($_POST['survey_id']);
        self::message(__('Umfrage gelöscht.', 'LL_survey'));
        wp_redirect(self::admin_url() . self::admin_page_surveys);
        exit;
      }
    }
    wp_redirect(self::admin_url() . self::admin_page_surveys);
    exit;
  }



  static function shortcode_TEST($atts)
  {
    return 'TEST';
  }



  static function init_hooks_and_filters()
  {
    add_action('admin_notices', self::_('admin_notices'));

    self::hook_admin_menu();

    add_shortcode(self::shortcode_TEST['code'], self::_('shortcode_TEST'));

    register_activation_hook(__FILE__, self::_('activate'));
    register_deactivation_hook(__FILE__, self::_('uninstall'));

    add_action('rest_api_init', function ()
    {
      register_rest_route(self::_ . '/v1', 'get', [
        'callback' => self::_('json_get')
      ]);
    });
  }
}

LL_survey::init_hooks_and_filters();

?>