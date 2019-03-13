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

  const shortcode_SURVEY                    = ['code'    => 'LL_SURVEY',
                                               'html'    => '[LL_SURVEY [title | start | end] #&lt;id&gt;]'];

  const q_type_text = 'text';
  const q_type_check = 'check';
  const q_type_select = 'select';
  const q_type_multiselect = 'multiselect';

  const q_types_with_extra_singleline = [self::q_type_text];
  const q_types_with_extra_multiline = [self::q_type_check, self::q_type_select, self::q_type_multiselect];

  const list_item = '<span style="padding: 5px;">&ndash;</span>';
  const arrow_up = '&#x2934;';
  const arrow_down = '&#x2935;';
  const secondary_settings_label = 'style="vertical-align: baseline;"';



	static function _($member_function) { return [self::_, $member_function]; }

	static function db_($table) { global $wpdb; return $wpdb->prefix . $table; }

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
    if (is_array($key)) {
      if (count($key) == 1 && isset($key[0])) {
        return $key[0];
      }
      if (count($key) > 1 && isset($key[0])) {
        $ret = '';
        if (isset($key['.'])) {
          $ret .=  self::escape_key($key['.']) . '.';
        }
        $ret .= self::escape_key($key[0]);
        if (isset($key['as'])) {
          $ret .= ' AS ' . self::escape_key($key['as']);
        }
        return $ret;
      }
    }
    if (strpos(strval($key), '`') !== false) {
      return $key;
    }
    return '`' . $key . '`';
  }

  static function escape_keys($keys)
  {
    return array_map(function($key) {
      return self::escape_key($key);
    }, $keys);
  }

  static function escape_value($value)
  {
    if (is_null($value))
      return 'NULL';
    else if (is_array($value) && count($value) == 1 && isset($value[0]))
      return $value[0];
    else
      return '"' . $value . '"';
  }

  static function _sql_escape_values($values)
  {
    return array_map(function($val) {
      return self::escape_value($val);
    }, $values);
  }

  static function _sql_escape($assoc_array, $escape_keys_only = false)
  {
    $ret = [];
    foreach ($assoc_array as $key => &$val) {
      $ret[self::escape_key($key)] = $escape_keys_only ? $val : self::escape_value($val);
    }
    return $ret;
  }

  static function _sql_from($tables)
  {
    $ret = ' FROM ';
    if (is_array($tables)) {
      if (count($tables) == 1 && isset($tables[0])) {
        $ret .= $tables[0];
      }
      else if (isset($tables['join']) || isset($tables['left join']) || isset($tables['right join']) || isset($tables['inner join'])) {
        foreach(['join', 'left join', 'right join', 'inner join'] as $join_type) {
          if (isset($tables[$join_type])) {
            $join = ' ' . strtoupper($join_type) . ' ';
            $on = $tables[$join_type];
            unset($tables[$join_type]);
            break;
          }
        }
        $tables = self::escape_keys($tables);
        $ret .= implode($join, $tables) . ' ON ' . $on;
      }
    }
    else {
      $ret .= self::escape_key($tables);
    }
    return $ret;
  }

  static function _sql_where($where)
  {
    if (empty($where)) {
      return '';
    }
    $ret = [];
    foreach ($where as $key => &$value) {
      if (isset($value[1])) {
        $ret[] = self::escape_key($key) . ' ' . $value[0] . ' ' . self::escape_value($value[1]);
      }
      else {
        $ret[] = self::escape_key($key) . ' ' . $value[0];
      }
    }
    return ' WHERE ' . implode(' AND ', $ret);
  }

  static function _sql_orderby($orderby)
  {
    if (empty($orderby)) {
      return '';
    }
    return self::array_zip(' ', self::_sql_escape($orderby, true), ', ', ' ORDER BY ');
  }

  static function _sql_groupby($groupby)
  {
    if (empty($groupby)) {
      return '';
    }
    return ' GROUP BY ' . implode(', ', self::escape_keys($groupby));
  }

  static function _sql_what($what)
  {
    if (empty($what)) {
      return '';
    }
    return implode(', ', self::escape_keys($what));
  }

  static function _db_build_select($tables, $what, $where, $groupby, $orderby)
  {
    $sql = 'SELECT ' . self::_sql_what($what) . self::_sql_from($tables) . self::_sql_where($where) . self::_sql_groupby($groupby) . self::_sql_orderby($orderby) . ';';
    // self::message($sql);
    return $sql;
  }

  static function _db_insert($table, $data, $timestamp_key = null)
  {
    $data = self::_sql_escape($data);
    if (!is_null($timestamp_key))
      $data[self::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'INSERT INTO ' . self::escape_key($table) . ' ( ' . implode(', ', array_keys($data)) . ' ) VALUES ( ' . implode(', ', array_values($data)) . ' );';
    // self::message($sql);
    if ($wpdb->query($sql))
      $result = $wpdb->insert_id;
    else
      $result = false;
    if ($wpdb->last_error) self::message('<i>(_db_insert)</i><hr />' . $wpdb->last_error . '<hr />' . $wpdb->last_query);
    return $result;
  }

  static function _db_update($table, $data, $where, $timestamp_key = null)
  {
    $data = self::_sql_escape($data);
    if (!is_null($timestamp_key))
      $data[self::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'UPDATE ' . self::escape_key($table) . ' SET ' . self::array_zip(' = ', $data, ', ') . self::_sql_where($where) . ';';
    // self::message($sql);
    $result = $wpdb->query($sql);
    if ($wpdb->last_error) self::message('<i>(_db_update)</i><hr />' . $wpdb->last_error . '<hr />' . $wpdb->last_query);
    return $result;
  }

  static function _db_delete($table, $where)
  {
    global $wpdb;
    $sql = 'DELETE FROM ' . self::escape_key($table) . self::_sql_where($where) . ';';
    // self::message($sql);
    $result = $wpdb->query($sql);
    if ($wpdb->last_error) self::message('<i>(_db_delete)</i><hr />' . $wpdb->last_error . '<hr />' . $wpdb->last_query);
    return $result;
  }

  static function _db_select($tables, $what = [['*']], $where = [], $groupby = [], $orderby = [])
  {
    global $wpdb;
    $result = $wpdb->get_results(self::_db_build_select($tables, $what, $where, $groupby, $orderby), ARRAY_A);
    if ($wpdb->last_error) self::message('<i>(_db_select)</i><hr />' . $wpdb->last_error . '<hr />' . $wpdb->last_query);
    return $result;
  }

  static function _db_select_row($tables, $what = [['*']], $where = [], $groupby = [], $orderby = [])
  {
    global $wpdb;
    $result = $wpdb->get_row(self::_db_build_select($tables, $what, $where, $groupby, $orderby), ARRAY_A);
    if ($wpdb->last_error) self::message('<i>(_db_select_row)</i><hr />' . $wpdb->last_error . '<hr />' . $wpdb->last_query);
    return $result;
  }



  // surveys
  // - id
  // - name
  // - preview
  // - start
  // - end
  static function db_add_survey($title) { return self::_db_insert(self::db_(self::table_surveys), ['title' => $title]); }
  static function db_update_survey($survey_id, $data) { return self::_db_update(self::db_(self::table_surveys), $data, ['id' => ['=', $survey_id]]); }
  static function db_get_surveys($what = [['*']]) { return self::_db_select(self::db_(self::table_surveys), $what); }
  static function db_get_surveys_with_count() { return self::_db_select([[self::db_(self::table_surveys), 'as' => 's'], [self::db_(self::table_questions), 'as' => 'q'], 'left join' => '`s`.`id` = `q`.`survey`'], [['.' => 's', 'id', 'as' => 'id'], ['.' => 's', 'title', 'as' => 'title'], ['.' => 's', 'preview', 'as' => 'preview'], ['.' => 's', 'start', 'as' => 'start'], ['.' => 's', 'end', 'as' => 'end'], [['COUNT(0)'], 'as' => 'num-questions']], [], [['.' => 's', 'id']]); }
  static function db_get_survey_by_id($survey_id) { return self::_db_select_row(self::db_(self::table_surveys), ['id', 'title', 'preview', 'start', 'end', ['DATE_FORMAT(`start`, "%Y-%m-%dT%H:%i") AS `start_T`'], ['DATE_FORMAT(`end`, "%Y-%m-%dT%H:%i") AS `end_T`']], ['id' => ['=', $survey_id]]); }
  static function db_delete_survey($survey_id) { return self::_db_delete(self::db_(self::table_surveys), ['id' => ['=', $survey_id]]); }

  // questions
  // - id
  // - survey
  // - text
  // - type
  // - extra
  // - reuse_extra
  // - in_matrix
  // - position
  static function db_add_question($survey_id, $text, $type, $extra, $reuse_extra, $in_matrix, $position) { return self::_db_insert(self::db_(self::table_questions), ['survey' => $survey_id, 'text' => $text, 'type' => $type, 'extra' => $extra, 'reuse_extra' => $reuse_extra, 'in_matrix' => $in_matrix, 'position' => $position]); }
  static function db_update_question($question_id, $text, $type, $extra, $reuse_extra, $in_matrix, $position) { return self::_db_update(self::db_(self::table_questions), ['text' => $text, 'type' => $type, 'extra' => $extra, 'reuse_extra' => $reuse_extra, 'in_matrix' => $in_matrix, 'position' => $position], ['id' => ['=', $question_id]]); }
  static function db_delete_question($question_id) { return self::_db_delete(self::db_(self::table_questions), ['id' => ['=', $question_id]]); }
  static function db_get_questions_by_survey($survey_id, $what = [['*']]) { return self::_db_select(self::db_(self::table_questions), $what, ['survey' => ['=', $survey_id]], [], ['position' => 'ASC']); }



  static function activate()
  {
    global $wpdb;
    $r = [];

    $r[] = self::db_(self::table_surveys ). ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_key(self::db_(self::table_surveys)) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` varchar(200) NOT NULL,
        `preview` tinyint(1) NOT NULL DEFAULT \'1\',
        `start` datetime DEFAULT NULL,
        `end` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    $r[] = self::db_(self::table_questions ). ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_key(self::db_(self::table_questions)) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `survey` int(10) UNSIGNED NOT NULL,
        `text` text NOT NULL,
        `type` varchar(20) NOT NULL,
        `extra` text NULL,
        `reuse_extra` int(10) UNSIGNED NULL,
        `in_matrix` tinyint(1) NOT NULL,
        `position` int(10) UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`survey`) REFERENCES ' . self::escape_key(self::db_(self::table_surveys)) . ' (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (`reuse_extra`) REFERENCES ' . self::escape_key(self::db_(self::table_questions)) . ' (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    $r[] = self::db_(self::table_answers ). ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_key(self::db_(self::table_answers)) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `question` int(10) UNSIGNED NOT NULL,
        `text` text NOT NULL,
        `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`question`) REFERENCES ' . self::escape_key(self::db_(self::table_questions)) . ' (`id`) ON DELETE CASCADE ON UPDATE CASCADE
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);
    
    self::message('Datenbank eingerichtet.<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');


//    add_option(self::option_test, 'test');

    self::message('Optionen initialisiert.');


//    register_uninstall_hook(__FILE__, self::_('uninstall'));
  }

  static function uninstall()
  {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys(self::db_(self::table_answers)) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys(self::db_(self::table_questions)) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys(self::db_(self::table_surveys)) . ';');

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

    add_submenu_page(self::admin_page_settings,           self::_, 'Einstellungen', $required_capability, self::admin_page_settings,  self::_('admin_page_settings'));
    $suffix = add_submenu_page(self::admin_page_settings, self::_, 'Umfragen',      $required_capability, self::admin_page_surveys,   self::_('admin_page_surveys'));
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
      <h1><?=__('Neue Umfrage erstellen', 'LL_survey')?></h1>

      <form method="post" action="admin-post.php">
        <input type="hidden" name="action" value="<?=self::_?>_survey_action" />
        <?php wp_nonce_field(self::_ . '_survey_add'); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Umfragetitel', 'LL_survey')?></th>
            <td>
              <input type="text" name="survey_title" placeholder="<?=__('Meine Umfrage', 'LL_survey')?>" class="regular-text" /> &nbsp;
              <?php submit_button(__('Neue Umfrage anlegen', 'LL_survey'), 'primary', '', false); ?>
            </td>
          </tr>
        </table>
      </form>

      <hr />

      <h1><?=__('Umfragen', 'LL_survey')?></h1>

      <style>
        table.<?=self::_?>_overview {
          margin-top: 10px;
          width: 100%;
          border-collapse: collapse;
        }
        table.<?=self::_?>_overview a {
          text-decoration: none;
        }
        table.<?=self::_?>_overview td[rowspan="2"] {
          font-size: 200%;
          color: #aaa;
          width: 80px;
        }
        table.LL_survey_overview tr:nth-child(4n-3) td, table.LL_survey_overview tr:nth-child(4n-2) td {
          background: #fff5;
        }
        table.LL_survey_overview tr:nth-child(2n-1) td:nth-child(2) {
          padding-top: 10px;
        }
        table.LL_survey_overview tr:nth-child(2n) td {
          padding-bottom: 10px;
        }
        table.<?=self::_?>_overview td.nostretch {
          width: 1px;
          white-space: nowrap;
        }
        table.<?=self::_?>_overview td > span {
          padding: 0 20px;
        }
      </style>
      <table class="<?=self::_?>_overview">
        <?php
        $surveys = self::db_get_surveys_with_count();
        $edit_url = self::admin_url() . self::admin_page_survey_edit;
        foreach ($surveys as &$survey) {
?>
          <tr>
            <td rowspan="2">#<?=$survey['id']?></td>
            <td colspan="4"><a href="<?=$edit_url . $survey['id']?>"><b><?=$survey['title']?></b></a></td>
          </tr>
          <tr>
            <td class="nostretch"><?=sprintf(__('%d Fragen', 'LL_survey'), $survey['num-questions'])?></td>
            <td class="nostretch"><span>&middot;</span> <?=sprintf(__('%d Teilnehmer', 'LL_survey'), $survey['num-answers'])?></td>
            <td class="nostretch"><span>&middot;</span> <?=$survey['start'] ?: '...'?> &mdash; <?=$survey['end'] ?: '...'?></td>
            <td></td>
          </tr>
<?php
        }
?>
      </table>
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
      <h1><?=__('Umfragen', 'LL_survey')?> &gt; #<?=$survey['id']?> <?=$survey['title']?></h1>

      <form method="post" action="admin-post.php">
        <input type="hidden" name="action" value="<?=self::_?>_survey_action" />
        <?php wp_nonce_field(self::_ . '_survey_edit'); ?>
        <input type="hidden" name="survey_id" value="<?=$survey_id?>" />
        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Umfragetitel', 'LL_survey')?></th>
            <td>
              <input type="text" name="title" value="<?=$survey['title']?>" style="width: 100%;" />
            </td>
          </tr>
          <tr>
            <th scope="row"><?=__('Zeitraum', 'LL_survey')?></th>
            <td>
              <input type="datetime-local" name="start" value="<?=$survey['start_T']?>" title="<?=__('Startzeitpunkt', 'LL_survey')?>" />
              &nbsp;&mdash;&nbsp;
              <input type="datetime-local" name="end" value="<?=$survey['end_T']?>" title="<?=__('Endzeitpunkt', 'LL_survey')?>" />
              <p class="description">
                <?=__('Kein Startzeitpunkt: ab sofort', 'LL_survey')?><br />
                <?=__('Kein Endzeitpunkt: unbegrenze Dauer', 'LL_survey')?>
              </p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?=__('Vorschaumodus', 'LL_survey')?></th>
            <td>
              <input type="checkbox" name="preview" id="preview" <?=$survey['preview'] ? 'checked' : ''?> />
              <label for="preview">&nbsp; <span class="description"><?=__('Sichtbar nur für eingeloggte Nutzer', 'LL_survey')?></span></label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?=__('Fragen', 'LL_survey')?></th>
            <td>
              <style>
                #<?=self::_?>_questions_div > div {
                  display: flex;
                  flex-direction: row;
                }
                #<?=self::_?>_questions_div > div > div {
                  flex: 1;
                  display: inline-block;
                }
                #<?=self::_?>_questions_div > div > div > *, #<?=self::_?>_questions_div .extra_div > input[type="text"], #<?=self::_?>_questions_div .extra_div > textarea {
                  width: 100%;
                }
                #<?=self::_?>_questions_div .extra_div > textarea {
                  background: linear-gradient(white 1px, transparent 1px) 0 0 / auto 100% content-box, linear-gradient(#CCC 1px, transparent 1px) 0 0 / auto 19px content-box, white;
                  resize: none;
                }
                #<?=self::_?>_questions_div .dashicons {
                  color: #ccc;
                  text-align: left;
                  padding-top: 2px;
                  font-size: 26px;
                  width: 36px;
                  height: auto;
                }
                #<?=self::_?>_questions_div input[type="text"] {
                  height: 28px;
                }
              </style>
              <div id="<?=self::_?>_questions_div">
<?php
        function print_question_html($i, $question) {
          $t = $question['type'];
?>
                <div id="<?=is_null($question) ? self::_ . '_add_question_template' : ''?>">
                  <input type="hidden" name="q_order_<?=$i?>" value="<?=$i?>" />
                  <input type="hidden" name="q_id_<?=$i?>" value="<?=$question['id'] ?? ''?>" />
                  <span class="dashicons dashicons-sort"></span>
                  <select name="q_type_<?=$i?>">
                    <option value="<?=self::q_type_text?>" <?=$t == self::q_type_text ? 'selected' : ''?>><?=__('Text', 'LL_survey')?></option>
                    <option value="<?=self::q_type_check?>" <?=$t == self::q_type_check ? 'selected' : ''?>><?=__('Check', 'LL_survey')?></option>
                    <option value="<?=self::q_type_select?>" <?=$t == self::q_type_select ? 'selected' : ''?>><?=__('Auswahl', 'LL_survey')?></option>
                    <option value="<?=self::q_type_multiselect?>" <?=$t == self::q_type_multiselect ? 'selected' : ''?>><?=__('Mehrfachauswahl', 'LL_survey')?></option>
                  </select>
                  <div>
                    <input type="text" name="q_text_<?=$i?>" placeholder="<?=__('Was willst du wissen?')?>" value="<?=$question['text'] ?? ''?>" />
                    <div class="extra_div">
                      <input type="text" name="q_extra_singleline_<?=$i?>" placeholder="<?=__('Option')?>" value="<?=$question['extra'] ?? ''?>" />
                      <textarea name="q_extra_multiline_<?=$i?>" rows="1" placeholder="<?=__('Option 1...')?>"><?=$question['extra'] ?? ''?></textarea>
                      <div class="reuse_extra_div">
                        <label style="margin-right: 20px;"><input type="checkbox" name="q_reuse_extra_<?=$i?>" <?=is_null($question['reuse_extra']) ? '' : 'checked'?> /> <?=__('Dieselben Optionen wie darüber', 'LL_survey')?></label>
                        <label><input type="checkbox" name="q_in_matrix_<?=$i?>" <?=$question['in_matrix'] ? 'checked' : ''?> /> <?=__('Zusammen mit der Frage darüber als Matrix anzeigen', 'LL_survey')?></label>
                      </div>
                    </div>
                  </div>
                </div>
<?php
        }
        $questions = self::db_get_questions_by_survey($survey_id);
        $i = 0;
        foreach ($questions as $question) {
          $t = $question['type'];
          print_question_html($i, $question);
          ++$i;
        }
?>
              </div>
              <p class="description">
                <?=sprintf(__('Ziehe %s hoch/runter um die Fragen zu sortieren.', 'LL_survey'), '<span class="dashicons dashicons-sort" style="color: #ccc;"></span>')?><br />
                <?=__('Leere das Textfeld einer Frage um sie (beim Speichern) zu löschen.', 'LL_survey')?>
              </p>
              <div style="display: none">
<?php
        print_question_html('', null);
?>
              </div>
              <p>
                <button id="<?=self::_?>_add_question_btn" class="button" type="button"><?=__('Frage hinzufügen', 'LL_survey')?></button>
              </p>
            </td>
          </tr>
          <tr>
            <td style="vertical-align: top;"><?php submit_button(__('Änderungen speichern', 'LL_survey'), 'primary', '', false); ?></td>
          </tr>
        </table>
      </form>
      <script>
        jQuery(function() {
          var questions_div = document.querySelector('#<?=self::_?>_questions_div');
          var jq_questions_div = jQuery(questions_div);
          var template = document.querySelector('#<?=self::_?>_add_question_template');
          var add_question_btn = document.querySelector('#<?=self::_?>_add_question_btn');
          function make_sortable() {
            jq_questions_div.sortable({
              axis: 'y',
              containment: 'parent',
              stop: function() {
                jq_questions_div.children().each(function(idx, item) {
                  item.querySelector('[name^="q_order_"]').value = idx;
                });
              }
            });
            jq_questions_div.disableSelection();
          }
          function on_select_type_show_hide_extra_div() {
            var select_type = this;
            var extra_div = select_type.parentNode.querySelector('.extra_div');
            jQuery(extra_div.querySelector('[name^="q_extra_multiline_"]')).each(on_input_extra_text_update_rows);
            jQuery(extra_div.querySelector('[name^="q_extra_"]')).each(on_input_text_show_hide_reuse_extra_checkbox);
            jQuery(extra_div.querySelector('[name^="q_reuse_extra_"]')).each(on_check_reuse_extra_show_hide_extra_textbox_and_in_matrix_checkbox);
            jQuery(extra_div.querySelector('[name^="q_in_matrix_"]')).each(on_check_in_matrix_show_hide_reuse_extra_checkbox);
          }
          function on_input_extra_text_update_rows() {
            var textarea_extra = this;
            textarea_extra.rows = Math.max(1, textarea_extra.value.split("\n").length);
          }
          function on_input_text_show_hide_reuse_extra_checkbox() {
            var text_input = this;
            var reuse_extra_div = text_input.parentNode.querySelector('.reuse_extra_div');
            if (text_input.value.length === 0) {
              reuse_extra_div.style.display = '';
            }
            else {
              reuse_extra_div.style.display = 'none';
              reuse_extra_div.querySelector('input[name^="q_reuse_extra_"]').checked = false;
              reuse_extra_div.querySelector('input[name^="q_in_matrix_"]').checked = false;
            }
          }
          function on_check_reuse_extra_show_hide_extra_textbox_and_in_matrix_checkbox() {
            var checkbox_reuse_extra = this;
            var select_type = checkbox_reuse_extra.parentNode.parentNode.parentNode.parentNode.parentNode.querySelector('[name^="q_type_"]');
            var is_singleline = ['<?=implode("', '", self::q_types_with_extra_singleline)?>'].includes(select_type.value);
            var is_multiline = ['<?=implode("', '", self::q_types_with_extra_multiline)?>'].includes(select_type.value);
            checkbox_reuse_extra.parentNode.parentNode.parentNode.querySelector('[name^="q_extra_singleline_"]').style.display = (is_singleline && !checkbox_reuse_extra.checked) ? '' : 'none';
            checkbox_reuse_extra.parentNode.parentNode.parentNode.querySelector('[name^="q_extra_multiline_"]').style.display = (is_multiline && !checkbox_reuse_extra.checked) ? '' : 'none';

            var checkbox_in_matrix = checkbox_reuse_extra.parentNode.parentNode.querySelector('[name^="q_in_matrix_"]');
            var label_checkbox_in_matrix = checkbox_in_matrix.parentNode;
            if (checkbox_reuse_extra.checked) {
              label_checkbox_in_matrix.style.display = '';
            }
            else {
              label_checkbox_in_matrix.style.display = 'none';
              checkbox_in_matrix.checked = false;
            }
          }
          function on_check_in_matrix_show_hide_reuse_extra_checkbox() {
            var checkbox_in_matrix = this;
            var label_checkbox_reuse_extra = checkbox_in_matrix.parentNode.parentNode.querySelector('[name^="q_reuse_extra_"]').parentNode;
            label_checkbox_reuse_extra.style.display = checkbox_in_matrix.checked ? 'none' : '';
          }
          jQuery(questions_div.querySelectorAll('[name^="q_type_"]')).on('change', on_select_type_show_hide_extra_div).each(on_select_type_show_hide_extra_div);
          jQuery(questions_div.querySelectorAll('[name^="q_extra_multiline_"]')).on('input', on_input_extra_text_update_rows);
          jQuery(questions_div.querySelectorAll('[name^="q_extra_"]')).on('input', on_input_text_show_hide_reuse_extra_checkbox);
          jQuery(questions_div.querySelectorAll('[name^="q_reuse_extra_"]')).on('change', on_check_reuse_extra_show_hide_extra_textbox_and_in_matrix_checkbox);
          jQuery(questions_div.querySelectorAll('[name^="q_in_matrix_"]')).on('change', on_check_in_matrix_show_hide_reuse_extra_checkbox);

          jQuery(add_question_btn).click(function() {
            var t_clone = template.cloneNode(true);
            var question_divs = jq_questions_div.children('div');
            var i = question_divs.length;

            t_clone.id = '';
            t_clone.querySelector('[name="q_order_"]').value = i;
            t_clone.querySelector('[name="q_order_"]').name += i;
            t_clone.querySelector('[name="q_id_"]').name += i;
            t_clone.querySelector('[name="q_type_"]').name += i;
            t_clone.querySelector('[name="q_text_"]').name += i;
            t_clone.querySelector('[name="q_extra_singleline_"]').name += i;
            t_clone.querySelector('[name="q_extra_multiline_"]').name += i;
            t_clone.querySelector('[name="q_reuse_extra_"]').name += i;
            t_clone.querySelector('[name="q_in_matrix_"]').name += i;

            if (i > 0) {
              var last = question_divs.last()[0];
              t_clone.querySelector('[name^="q_type_"]').value = last.querySelector('[name^="q_type_"]').value;
              t_clone.querySelector('[name^="q_reuse_extra_"]').checked = last.querySelector('[name^="q_reuse_extra_"]').checked;
            }

            jQuery(t_clone.querySelector('[name^="q_type_"]')).on('change', on_select_type_show_hide_extra_div).each(on_select_type_show_hide_extra_div);
            jQuery(t_clone.querySelector('[name^="q_extra_multiline_"]')).on('input', on_input_extra_text_update_rows);
            jQuery(t_clone.querySelector('[name^="q_extra_"]')).on('input', on_input_text_show_hide_reuse_extra_checkbox);
            jQuery(t_clone.querySelector('[name^="q_reuse_extra_"]')).on('change', on_check_reuse_extra_show_hide_extra_textbox_and_in_matrix_checkbox);
            jQuery(t_clone.querySelector('[name^="q_in_matrix_"]')).on('change', on_check_in_matrix_show_hide_reuse_extra_checkbox);

            questions_div.appendChild(t_clone);

            make_sortable();
          });
          make_sortable();
        });
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
        if (!empty($_POST['survey_title'])) {
          $survey_id = self::db_add_survey(trim($_POST['survey_title']));
          self::message(__('Neue Umfrage angelegt.', 'LL_survey'));
          wp_redirect(self::admin_url() . self::admin_page_survey_edit . $survey_id);
          exit;
        }
      }

      else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_survey_edit')) {
        $survey_id = $_POST['survey_id'];
        self::db_update_survey($survey_id, [
          'title' => $_POST['title'] ?? 0,
          'preview' => $_POST['preview'] ? 1 : 0,
          'start' => $_POST['start'] ?: null,
          'end' => $_POST['end'] ?: null]);
        self::message(__('Umfragedaten aktualisiert.', 'LL_survey'));

        $questions = [];
        $questions_updated = 0;
        $questions_added = 0;
        $questions_deleted = 0;
        $questions_not_added = 0;
        $i = 0;
        while (isset($_POST['q_id_' . $i])) {
          $question = [];
          $question['id'] = $_POST['q_id_' . $i] ?: null;
          $question['text'] = trim($_POST['q_text_' . $i]);
          $question['type'] = $_POST['q_type_' . $i];
          $can_have_singleline_extra = in_array($question['type'], self::q_types_with_extra_singleline);
          $can_have_multiline_extra = in_array($question['type'], self::q_types_with_extra_multiline);
          $question['extra'] = null;
          if ($can_have_singleline_extra)
            $question['extra'] = $_POST['q_extra_singleline_' . $i] ?: null;
          if ($can_have_multiline_extra)
            $question['extra'] = $_POST['q_extra_multiline_' . $i] ?: null;
          $question['reuse_extra'] = ($can_have_singleline_extra || $can_have_multiline_extra) && is_null($question['extra']) && $_POST['q_reuse_extra_' . $i];
          $question['in_matrix'] = $question['reuse_extra'] && $_POST['q_in_matrix_' . $i];
          if (!empty($question['text'])) {
            if (!is_null($question['id'])) {
              ++$questions_updated;
            }
            else {
              ++$questions_added;
            }
            $questions[intval($_POST['q_order_' . $i])] = $question;
          }
          else {
            if (!is_null($question['id'])) {
              self::db_delete_question($question['id']);
              ++$questions_deleted;
            }
            else {
              ++$questions_not_added;
            }
          }
          ++$i;
        }
        $i = 0;
        ksort($questions);
        $previous_type = null;
        $previous_id = null;
        foreach ($questions as $question) {
          if ($previous_type !== $question['type']) {
            $previous_id = null;
          }

          if (is_null($question['id'])) {
            $question['id'] = self::db_add_question($survey_id, $question['text'], $question['type'], $question['extra'], $question['reuse_extra'] ? $previous_id : null, $i);
          }
          else {
            self::db_update_question($question['id'], $question['text'], $question['type'], $question['extra'], $question['reuse_extra'] ? $previous_id : null, $i);
          }

          $previous_type = $question['type'];
          if (!$question['reuse_extra']) {
            $previous_id = $question['id'];
          }
          ++$i;
        }
        if ($questions_updated > 0) self::message(sprintf(__('%d Frage(n) aktualisiert', 'LL_survey'), $questions_updated));
        if ($questions_added > 0) self::message(sprintf(__('%d neue Frage(n) hinzugefügt', 'LL_survey'), $questions_added));
        if ($questions_deleted > 0) self::message(sprintf(__('%d Frage(n) gelöscht', 'LL_survey'), $questions_deleted));
        if ($questions_not_added > 0) self::message(sprintf(__('%d neue leere Frage(n) ignoriert', 'LL_survey'), $questions_not_added));

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



  // surveys
  // - id
  // - name
  // - preview
  // - start
  // - end
  // questions
  // - id
  // - survey
  // - text
  // - type
  // - extra
  // - reuse_extra
  // - in_matrix
  // - position
  static function shortcode_SURVEY($atts)
  {
    $what = $survey_id = strtolower($atts[0]);
    switch($what) {
      case 'title':
      case 'start':
      case 'end':
      case 'num-questions':
        $survey_id = $atts[1];
        break;
      default:
        $what = 'survey';
    }
    $survey_id = preg_filter('/^#?(\d+)$/', '$1', $survey_id);
    $survey = self::db_get_survey_by_id($survey_id);

    switch($what) {
      case 'title':
        return $survey['title'];
      case 'start':
        return $survey['start'];
      case 'end':
        return $survey['end'];
      case 'num-questions':
        return print_r(self::db_get_questions_by_survey($survey_id, [[['COUNT(0)'], 'as' => 'count']])[0]['count'], true);
        break;
      default:
        $what = 'survey';
    }

    ob_start();
    if (!$survey['preview'] || is_user_logged_in()) {
      $questions = self::db_get_questions_by_survey($survey_id);
      $questions_by_id = [];
      $max_num_matrix_options = 1;
      foreach ($questions as $idx => &$question) {
        $question['is_first_in_reuse_chain'] = !$question['reuse_extra'] && count($questions) > ($idx + 1) && $questions[$idx + 1]['reuse_extra'];
        if (!is_null($question['extra']) && in_array($question['type'], self::q_types_with_extra_multiline)) {
          $question['extra'] = explode("\n", $question['extra']) ?: [];
          $max_num_matrix_options = max($max_num_matrix_options, count($question['extra']));
        }
        $questions_by_id[$question['id']] = $question;
      }
      ?>
      <style>
        .<?=self::_?> th { width: 50%; }
        .<?=self::_?> td { width: <?=50 / $max_num_matrix_options?>%; }
      </style>
      <table class="<?=self::_?>">
      <?php
      foreach ($questions as $idx => &$question) {
        $tag_id_value = 'q_' . $question['id'];
        $tag_name = 'name="' . $tag_id_value . '"';
        $tag_name_and_id = $tag_name . ' id="' . $tag_id_value . '"';
        $extra = ($question['reuse_extra'] ? $questions_by_id[$question['reuse_extra']] : $question)['extra'];
        switch ($question['type']) {
          case self::q_type_text:
            ?>
            <tr>
              <th class="<?=self::_?>_question"><?=$question['text']?></th>
              <td class="<?=self::_?>_question_text" colspan="<?=$max_num_matrix_options?>">
                <input type="text" <?=$tag_name_and_id?> />
              </td>
            </tr>
            <?php
            break;

          case self::q_type_check:
            ?>
            <tr>
              <th class="<?=self::_?>_question"><?=$question['text']?></th>
              <td class="<?=self::_?>_question_check" colspan="<?=$max_num_matrix_options?>">
                <input type="checkbox" <?=$tag_name_and_id?> /><label for="<?=$tag_id_value?>" data-on="Ja" data-off="Nein"></label>
              </td>
            </tr>
            <?php
            break;

          case self::q_type_select:
            if ($question['is_first_in_reuse_chain']) {
              ?>
              <tr>
                <th>Matrix<?=$question['id']?></th>
                <?php
                foreach ($extra as &$option) {
                  ?>
                  <td class="<?= self::_ ?>_question_select_matrix_header">
                    <span><?=$option?></span>
                  </td>
                  <?php
                }
                ?>
              </tr>
              <?php
            }
            if ($question['reuse_extra'] || $question['is_first_in_reuse_chain']) {
              ?>
              <tr>
                <th class="<?=self::_?>_question"><?= $question['text'] ?><?=$question['id']?></th>
                <?php
                foreach ($extra as $idx => &$option) {
                  $tag_id_value_with_idx = $tag_id_value . '_' . $idx;
                  ?>
                  <td class="<?= self::_ ?>_question_select <?= self::_ ?>_question_select_matrix">
                    <input type="radio" <?=$tag_name?> id="<?=$tag_id_value_with_idx?>" /><label for="<?=$tag_id_value_with_idx?>"></label>
                  </td>
                  <?php
                }
                ?>
              </tr>
              <?php
            }
            else {
              ?>
              <tr>
                <th class="<?=self::_?>_question"><?= $question['text'] ?><?=$question['id']?></th>
                <td class="<?= self::_ ?>_question_select">
                <?php
                foreach ($extra as $idx => &$option) {
                  $tag_id_value_with_idx = $tag_id_value . '_' . $idx;
                  ?>
                  <input type="radio" <?=$tag_name?> id="<?=$tag_id_value_with_idx?>" /><label for="<?=$tag_id_value_with_idx?>"> <?=$option?></label><br />
                  <?php
                }
                ?>
                </td>
              </tr>
              <?php
            }
            break;

          case self::q_type_multiselect:
            if ($question['is_first_in_reuse_chain']) {
              ?>
              <tr>
                <th>Matrix</th>
                <td class="<?= self::_ ?>_question_select_matrix_header">
                  (noch nicht verfügbar)
                </td>
              </tr>
              <?php
            }
            if ($question['reuse_extra'] || $question['is_first_in_reuse_chain']) {
              ?>
              <tr>
                <th class="<?=self::_?>_question"><?= $question['text'] ?></th>
                <td class="<?= self::_ ?>_question_select_matrix">
                  (noch nicht verfügbar)
                </td>
              </tr>
              <?php
            }
            else {
              ?>
              <tr>
                <th class="<?=self::_?>_question"><?= $question['text'] ?></th>
                <td class="<?= self::_ ?>_question_select">
                <?php
                foreach ($extra as $idx => &$option) {
                  $tag_id_value_with_idx = $tag_id_value . '_' . $idx;
                  $tag_name_and_id_with_idx = 'name="' . $tag_id_value_with_idx . '" id="' . $tag_id_value_with_idx . '"';
                  ?>
                  <input type="checkbox" <?=$tag_name_and_id_with_idx?> /><label for="<?=$tag_id_value_with_idx?>"> <?=$option?></label><br />
                  <?php
                }
                ?>
                </td>
              </tr>
              <?php
            }
            break;
        }
      }
      ?>
      </table>
      <?php
//      print_r($survey);
//      print_r($questions);
    }
    return ob_get_clean();
  }



  static function admin_enqueue_scripts()
  {
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-sortable');
  }



  static function init_hooks_and_filters()
  {
    add_action('admin_enqueue_scripts', self::_('admin_enqueue_scripts'));

    add_action('admin_notices', self::_('admin_notices'));

    self::hook_admin_menu();

    add_shortcode(self::shortcode_SURVEY['code'], self::_('shortcode_SURVEY'));

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