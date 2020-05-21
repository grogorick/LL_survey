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
  const table_answers                       = self::_ . '_answers_';

  const admin_page_settings                 = self::_ . '_settings';
  const admin_page_surveys                  = self::_ . '_surveys';
  const admin_page_survey_edit              = self::_ . '_surveys&edit=';
  const admin_page_survey_answers           = self::_ . '_surveys&answers=';

  const shortcode_SURVEY                    = ['code'    => 'LL_SURVEY',
                                               'html'    => '[LL_SURVEY [title | start | end] #&lt;id&gt;]'];

  const q_type_text = 'text';
  const q_type_check = 'check';
  const q_type_select = 'select';
  const q_type_multiselect = 'multiselect';

  const q_type_special_hint = 'hint';
  const q_type_special_separator = '-';
  const q_type_special_delete = 'x';

  static function printable($slug)
  {
    switch ($slug) {
      case self::q_type_text: return __('Text', 'LL_survey');
      case self::q_type_check: return __('Ja/Nein', 'LL_survey');
      case self::q_type_select: return __('Auswahl', 'LL_survey');
      case self::q_type_multiselect: return __('Mehrfachauswahl', 'LL_survey');
      case self::q_type_special_hint: return __('(Hinweistext)', 'LL_survey');
      case self::q_type_special_separator: return __('(Seitenwechsel)', 'LL_survey');
      case self::q_type_special_delete: return __('(Löschen)', 'LL_survey');
      case self::q_special_text_multiline: return self::q_special_text_multiline . __('ANZAHL-ZEILEN', 'LL_survey');
      default: return '(non-printable slug)';
    }
  }

  const q_types_with_extra_singleline = [self::q_type_text];
  const q_types_with_extra_multiline = [self::q_type_check, self::q_type_select, self::q_type_multiselect];
  const q_types_select = [self::q_type_select, self::q_type_multiselect];
  const q_types_special = [self::q_type_special_hint, self::q_type_special_separator, self::q_type_special_delete];

  const q_special_text_multiline = 'multiline';
  const q_special_text_types = ['number', 'date', 'time', 'datetime-local', 'email', 'url'];

  const pattern_multiline = ['/^' . self::q_special_text_multiline . '(\s+(\d+))?$/', '$2'];
  const list_item = '<span style="padding: 5px;">&ndash;</span>';
  const arrow_up = '&#x2934;';
  const arrow_down = '&#x2935;';
  const secondary_settings_label = 'style="vertical-align: baseline;"';



	static function _($member_function) { return [self::_, $member_function]; }

	static function db_($table) { global $wpdb; return $wpdb->prefix . $table; }
	static function js_($array) { return "'" . implode("', '", $array) . "'"; }

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
    if (is_string($where)) {
      $ret[] = $where;
    }
    else {
      foreach ($where as $key => &$value) {
        if (isset($value[1])) {
          $ret[] = self::escape_key($key) . ' ' . $value[0] . ' ' . self::escape_value($value[1]);
        }
        else {
          $ret[] = self::escape_key($key) . ' ' . $value[0];
        }
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

  static function _db_build_select($tables, $what, $where = [], $groupby = [], $orderby = [])
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

  static function _db_delete_table($table)
  {
    global $wpdb;
    $result = $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_key($table) . ';');
    if ($wpdb->last_error) self::message('<i>(_db_delete_table)</i><hr />' . $wpdb->last_error . '<hr />' . $wpdb->last_query);
    return $result;
  }



  // surveys
  // - id
  // - title
  // - active
  // - start
  // - end
  // - redirect_page
  static function db_add_survey($title) { return self::_db_insert(self::db_(self::table_surveys), ['title' => $title]); }
  static function db_update_survey($survey_id, $data) { return self::_db_update(self::db_(self::table_surveys), $data, ['id' => ['=', $survey_id]]); }
  static function db_get_surveys($what = [['*']]) { return self::_db_select(self::db_(self::table_surveys), $what); }
  static function db_get_surveys_with_question_count() { return self::_db_select([[self::db_(self::table_surveys), 'as' => 's'], [self::db_(self::table_questions), 'as' => 'q'], 'left join' => '`s`.`id` = `q`.`survey`'], [['.' => 's', 'id', 'as' => 'id'], ['.' => 's', 'title', 'as' => 'title'], ['.' => 's', 'active', 'as' => 'active'], ['.' => 's', 'start', 'as' => 'start'], ['.' => 's', 'end', 'as' => 'end'], [['COUNT(0)'], 'as' => 'num-questions']], [], [['.' => 's', 'id']]); }
  static function db_get_survey_by_id($survey_id, $what = [['*'], ['DATE_FORMAT(`start`, "%Y-%m-%dT%H:%i") AS `start_T`'], ['DATE_FORMAT(`end`, "%Y-%m-%dT%H:%i") AS `end_T`']]) { return self::_db_select_row(self::db_(self::table_surveys), $what, ['id' => ['=', $survey_id]]); }
  static function db_delete_survey($survey_id) { return self::_db_delete(self::db_(self::table_surveys), ['id' => ['=', $survey_id]]); }

  // questions
  // - id
  // - survey
  // - position
  // - type
  // - text
  // - extra
  // - reuse_extra
  // - in_matrix
  // - required
  static function db_add_question($survey_id, $position, $type, $text, $extra, $reuse_extra, $in_matrix, $required) { return self::_db_insert(self::db_(self::table_questions), ['survey' => $survey_id, 'position' => $position, 'type' => $type, 'text' => $text, 'extra' => $extra, 'reuse_extra' => $reuse_extra, 'in_matrix' => $in_matrix, 'required' => $required]); }
  static function db_update_question($question_id, $position, $type, $text, $extra, $reuse_extra, $in_matrix, $required) { return self::_db_update(self::db_(self::table_questions), ['position' => $position, 'type' => $type, 'text' => $text, 'extra' => $extra, 'reuse_extra' => $reuse_extra, 'in_matrix' => $in_matrix, 'required' => $required], ['id' => ['=', $question_id]]); }
  static function db_delete_question($question_id) { return self::_db_delete(self::db_(self::table_questions), ['id' => ['=', $question_id]]); }
  static function db_get_questions_by_survey($survey_id, $what = [['*']]) { return self::_db_select(self::db_(self::table_questions), $what, ['survey' => ['=', $survey_id]], [], ['position' => 'ASC']); }
  static function db_get_questions_by_survey_with_reuse_extra($survey_id) {
	  return self::_db_select(
	    [[self::db_(self::table_questions), 'as' => 'q'], [self::db_(self::table_questions), 'as' => 'qq'], 'left join' => '`q`.`reuse_extra` = `qq`.`id`'],
      [['.' => 'q', ['*']], ['.' => 'qq', 'extra', 'as' => 'indirect_extra']],
      '`q`.`survey` = ' . self::escape_value($survey_id)); }

  // answers
  // - id
  // - time
  // - q_{ID 1}
  // - q_{ID 2}
  // - ...
  // - q_{ID n}
  static function db_add_answer($survey_id, $answer) { return self::_db_insert(self::db_(self::table_answers . $survey_id), $answer); }
  static function db_count_answers($survey_id) { return self::_db_select_row(self::db_(self::table_answers . $survey_id), [[['COUNT(0)'], 'as' => 'count']])['count']; }
  static function db_get_answers_by_survey($survey_id) { return self::_db_select(self::db_(self::table_answers . $survey_id)); }



  static function activate()
  {
    global $wpdb;
    $r = [];

    $r[] = self::db_(self::table_surveys ). ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_key(self::db_(self::table_surveys)) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` text NOT NULL,
        `active` tinyint(1) NOT NULL DEFAULT \'0\',
        `start` datetime DEFAULT NULL,
        `end` datetime DEFAULT NULL,
        `redirect_page` varchar(200) NULL
        PRIMARY KEY (`id`)
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    $r[] = self::db_(self::table_questions ). ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_key(self::db_(self::table_questions)) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `survey` int(10) UNSIGNED NOT NULL,
        `position` int(10) UNSIGNED NOT NULL,
        `type` varchar(20) NOT NULL,
        `text` text NULL,
        `extra` text NULL,
        `reuse_extra` int(10) UNSIGNED NULL,
        `in_matrix` tinyint(1) NULL,
        `required` tinyint(1) NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`survey`) REFERENCES ' . self::escape_key(self::db_(self::table_surveys)) . ' (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (`reuse_extra`) REFERENCES ' . self::escape_key(self::db_(self::table_questions)) . ' (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    self::message('Datenbank eingerichtet.<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');


//    add_option(self::option_test, 'test');

    self::message('Optionen initialisiert.');


//    register_uninstall_hook(__FILE__, self::_('uninstall'));
  }

  static function uninstall()
  {
    self::_db_delete_table(self::table_answers);
    self::_db_delete_table(self::table_questions);
    self::_db_delete_table(self::table_surveys);

    delete_option(self::option_msg);
    delete_option(self::option_test);
  }



  static function find_wp_post($slug)
  {
    global $wpdb;
    return (int) $wpdb->get_var(self::_db_build_select($wpdb->posts, ['ID'], ['post_name' => ['=', $slug]]));
  }

  static function get_post_edit_url($post_id)
  {
    return admin_url('post.php?action=edit&post=' . $post_id);
  }

  static function json_get($request)
  {
    if (isset($request['test'])) {
      return 'test';
    }
    else if (isset($request['find_post'])) {
      $id = self::find_wp_post($request['find_post']);
      $url = $id ? self::get_post_edit_url($id) : null;
      return array(
        'id'  => $id,
        'url' => $url);
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
    add_menu_page(self::_, self::_, $required_capability, self::admin_page_surveys, self::_('admin_page_surveys'), plugins_url('/icon.png', __FILE__));

    add_submenu_page(self::admin_page_surveys, self::_, 'Umfragen',      $required_capability, self::admin_page_surveys,   self::_('admin_page_surveys'));

    add_submenu_page(self::admin_page_surveys, self::_, 'Einstellungen', $required_capability, self::admin_page_settings,  self::_('admin_page_settings'));
    add_action('admin_init', self::_('admin_page_settings_general_action'));
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



  static function print_question_html($i, $question, $survey_active) {
    $t = $question['type'];
    $disabled = $survey_active ? 'disabled' : '';
    ?> 
    <div id="<?=is_null($question) ? self::_ . '_add_question_template' : ''?>">
      <input type="hidden" name="q_order_<?=$i?>" value="<?=$i?>" />
      <input type="hidden" name="q_id_<?=$i?>" value="<?=$question['id'] ?? ''?>" />
      <span class="dashicons dashicons-sort"></span>
      <select name="q_type_<?=$i?>">
        <option value="<?=self::q_type_text?>" <?=$t == self::q_type_text ? 'selected' : $disabled?>><?=self::printable(self::q_type_text)?></option>
        <option value="<?=self::q_type_check?>" <?=$t == self::q_type_check ? 'selected' : $disabled?>><?=self::printable(self::q_type_check)?></option>
        <option value="<?=self::q_type_select?>" <?=$t == self::q_type_select ? 'selected' : $disabled?>><?=self::printable(self::q_type_select)?></option>
        <option value="<?=self::q_type_multiselect?>" <?=$t == self::q_type_multiselect ? 'selected' : $disabled?>><?=self::printable(self::q_type_multiselect)?></option>
        <option disabled style="background: #f1f1f1;"></option>
        <option value="<?=self::q_type_special_hint?>" <?=$t == self::q_type_special_hint ? 'selected' : $disabled?>><?=self::printable(self::q_type_special_hint)?></option>
        <option value="<?=self::q_type_special_separator?>" <?=$t == self::q_type_special_separator ? 'selected' : $disabled?>><?=self::printable(self::q_type_special_separator)?></option>
        <option value="<?=self::q_type_special_delete?>"><?=self::printable(self::q_type_special_delete)?></option>
      </select>
      <hr style="display: none;" />
      <div class="input_div" style="display: none;">
        <input type="text" name="q_text_<?=$i?>" placeholder="<?=__('Was willst du wissen?')?>" value="<?=$question['text'] ?? ''?>" />
        <div class="extra_div">
          <input type="text" name="q_extra_singleline_<?=$i?>" placeholder="<?=__('Option')?>" value="<?=$question['extra'] ?? ''?>" />
          <textarea name="q_extra_multiline_<?=$i?>" rows="1" placeholder="<?=__('Option 1...')?>"><?=$question['extra'] ?? ''?></textarea>
          <div class="reuse_extra_div">
            <label style="display: inline-block; margin-right: 20px;"><input type="checkbox" name="q_reuse_extra_<?=$i?>"<?=is_null($question['reuse_extra']) ? '' : ' checked'?> /> <?=__('Dieselben Optionen wie darüber', 'LL_survey')?></label>
            <label style="display: inline-block;"><input type="checkbox" name="q_in_matrix_<?=$i?>"<?=$question['in_matrix'] ? ' checked' : ''?> /> <?=__('Zusammen mit der Frage darüber als Matrix anzeigen', 'LL_survey')?></label>
          </div>
          <label style="display: inline-block;"><input type="checkbox" name="q_required_<?=$i?>"<?=$question['required'] ? ' checked' : ''?> /> <?=__('Pflichtfeld', 'LL_survey')?></label>
        </div>
      </div>
    </div>
    <?php
  }

  static function admin_page_surveys()
  {
    if (isset($_GET['edit'])) $sub_page = 'edit';
    else if (isset($_GET['answers'])) $sub_page = 'answers';
    else $sub_page = 'list';
    ?> 
    <div class="wrap">
    <?php
    switch ($sub_page) {
      case 'list': self::admin_display_survey_list(); break;
      case 'edit': self::admin_display_edit_survey(); break;
      case 'answers': self::admin_display_survey_answers(); break;
    }
    ?>
    </div>
    <?php
  }

  static function admin_display_survey_list()
  {
    ?>
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
      table.<?=self::_?>_overview td {
        padding: 0;
      }
      table.<?=self::_?>_overview tr:first-child td:first-child {
        padding: 10px;
        font-size: 200%;
        color: #aaa;
        width: 80px;
      }
      table.LL_survey_overview tbody:nth-child(odd) td {
        background: #f9f9f9;
      }
      table.LL_survey_overview tr:nth-child(3n+1) td:nth-child(2) {
        padding-top: 10px;
      }
      table.LL_survey_overview tr:nth-child(3n) td {
        padding-bottom: 10px;
      }
      table.<?=self::_?>_overview td.nostretch {
        width: 1px;
        white-space: nowrap;
      }
      table.<?=self::_?>_overview td > span {
        padding: 0 20px;
      }
      table.<?=self::_?>_overview .has-row-actions:hover .row-actions {
        position: static;
      }
    </style>
    <table class="<?=self::_?>_overview widefat">
      <?php
      $surveys = self::db_get_surveys_with_question_count();
      $edit_url = self::admin_url() . self::admin_page_survey_edit;
      $answers_url = self::admin_url() . self::admin_page_survey_answers;
      foreach ($surveys as &$survey) {
        $num_answers = $survey['active'] ? sprintf(__('%d Teilnehmer', 'LL_survey'), self::db_count_answers($survey['id'])) : __('(inaktiv)', 'LL_survey');
        ?>
        <tbody class="has-row-actions">
          <tr>
            <td rowspan="3">#<?=$survey['id']?></td>
            <td colspan="4"><b><?=$survey['title']?></b></td>
          </tr>
          <tr>
            <td class="nostretch"><?=sprintf(__('%d Fragen', 'LL_survey'), $survey['num-questions'])?></td>
            <td class="nostretch"><span>&middot;</span> <?=$num_answers?></td>
            <td class="nostretch"><span>&middot;</span> <?=$survey['start'] ?: '...'?> &ndash; <?=$survey['end'] ?: '...'?></td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4">
              <div class="row-actions">
                <a href="<?=$edit_url . $survey['id']?>"><?=__('Umfrage bearbeiten', 'LL_survey')?></a>
                <?php
                if ($survey['active']) {
                  ?>
                  | <a href="<?=$answers_url . $survey['id']?>"><?=__('Antworten durchsuchen', 'LL_survey')?></a>
                  <?php
                }
                ?>
              </div>
            </td>
          </tr>
        </tbody>
        <?php
      }
      ?>
    </table>

    <hr style="margin-top: 20pt" />

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
    <?php
  }

  static function admin_display_edit_survey()
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
          <th scope="row"><?=__('Weiterleitung', 'LL_survey')?></th>
          <td>
            <input type="text" name="redirect_page" id="<?=self::_?>_redirect_page" class="regular-text" value="<?=$survey['redirect_page']?>" /> &nbsp; <span id="<?=self::_?>_redirect_page_response"></span>
            <p class="description">
              <?=__('Die Blog-Seite, die nach der Umfrage angezeigt werden soll.', 'LL_survey')?>
            </p>
          </td>
        </tr>
        <tr>
          <th scope="row"><?=__('Fragen', 'LL_survey')?></th>
          <td>
            <style>
              #<?=self::_?>_questions_div {
                margin-top: -50px;
                margin-bottom: -50px;
              }
              #<?=self::_?>_questions_div:before,
              #<?=self::_?>_questions_div:after {
                height: 50px;
                content: '';
                display: block;
              }
              #<?=self::_?>_questions_div > div {
                display: flex;
                flex-direction: row;
                margin-bottom: 20px;
              }
              #<?=self::_?>_questions_div > div > .input_div {
                flex: 1;
                display: inline-block;
              }
              #<?=self::_?>_questions_div > div > hr {
                border: 0;
                border-bottom: 1px dashed gray;
                flex: .9;
                height: 7px;
              }
              #<?=self::_?>_questions_div > div > div > *,
              #<?=self::_?>_questions_div .extra_div > input[type="text"],
              #<?=self::_?>_questions_div .extra_div > textarea {
                width: 100%;
              }
              #<?=self::_?>_questions_div .extra_div > textarea {
                line-height: 1.5;
                background: linear-gradient(white 1px, transparent 1px) 0 0 / auto 100% content-box, linear-gradient(#CCC 1px, transparent 1px) 0 0 / auto calc(1.5 * 1em) content-box, white;
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
              #<?=self::_?>_add_question_btn {
                margin: 0 0 20px 36px;
              }
            </style>
            <div id="<?=self::_?>_questions_div">
              <?php
              $questions = self::db_get_questions_by_survey($survey_id);
              $i = 0;
              foreach ($questions as $question) {
                self::print_question_html($i, $question, $survey['active']);
                ++$i;
              }
              ?>
            </div>
            <?php
            if (!$survey['active']) {
              ?>
              <div style="display: none">
                <?php
                self::print_question_html('', null, false);
                ?>
              </div>
              <p>
                <button id="<?=self::_?>_add_question_btn" class="button" type="button"><?=__('Frage hinzufügen', 'LL_survey')?></button>
              </p>
              <?php
            }
            ?>
            <p class="description">
              <?=sprintf(__('Ziehe %s hoch/runter um die Fragen zu sortieren.', 'LL_survey'), '<span class="dashicons dashicons-sort" style="color: #ccc;"></span>')?><br />
              <?=sprintf(__('Als Option für Text-Fragen kann ein %s oder einer der vordefinierten Typen %s verwendet werden.', 'LL_survey'), '<code>pattern</code>', '<code>' . self::printable(self::q_special_text_multiline) . ', ' . implode(', ', self::q_special_text_types) . '</code>')?>
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

        // GENERAL SURVEY

        timeout = {};
        function check_page_exists(tag_id) {
          var page_input = document.querySelector('#' + tag_id);
          var response_tag = document.querySelector('#' + tag_id + '_response');
          timeout[tag_id] = null;
          function check_now() {
            timeout[tag_id] = null;
            jQuery.getJSON('<?=self::json_url()?>get?find_post=' + page_input.value, function(post) {
              if (post.id > 0) {
                response_tag.innerHTML = '(<a href="' + post.url + '"><?=__('Zur Seite')?></a>)';
              }
              else {
                response_tag.innerHTML = '<span style="color: red;"><?=__('Seite nicht gefunden', 'LL_mailer')?></span>';
              }
            });
          }
          function check_later() {
            if (timeout[tag_id] !== null) {
              clearTimeout(timeout[tag_id]);
            }
            if (page_input.value === '') {
              response_tag.innerHTML = '';
              return;
            }
            response_tag.innerHTML = '...';
            timeout[tag_id] = setTimeout(check_now, 1000);
          }
          jQuery(page_input).on('input', check_later);
          if (page_input.value !== '') {
            check_now();
          }
        }
        check_page_exists('<?=self::_?>_redirect_page');


        // QUESTIONS

        var questions_div = document.querySelector('#<?=self::_?>_questions_div');
        var jq_questions_div = jQuery(questions_div);
        var template = document.querySelector('#<?=self::_?>_add_question_template');
        var add_question_btn = document.querySelector('#<?=self::_?>_add_question_btn');

        function make_sortable() {
          jq_questions_div.sortable({
            axis: 'y',
            containment: 'parent',
            start: function(event, ui) {
              ui.placeholder.css({
                'visibility': '',
                'border': '1px dashed gray'
              });
            },
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
          var separator_hr = select_type.parentNode.querySelector('hr');
          var input_div = select_type.parentNode.querySelector('.input_div');
          var extra_div = input_div.querySelector('.extra_div');
          var extra_singleline_input = jQuery(input_div.querySelector('[name^="q_extra_singleline"]'));
          var extra_multiline_textarea = jQuery(input_div.querySelector('[name^="q_extra_multiline_"]'));
          var reuse_extra_check = jQuery(input_div.querySelector('[name^="q_reuse_extra_"]'));
          var in_matrix_check = jQuery(input_div.querySelector('[name^="q_in_matrix_"]'));

          if (select_type.value === '<?=self::q_type_special_delete?>') {
            separator_hr.style.display = 'none';
            input_div.style.display = 'none';
          }
          else if (select_type.value === '<?=self::q_type_special_separator?>') {
            separator_hr.style.display = '';
            input_div.style.display = 'none';
          }
          else if (select_type.value === '<?=self::q_type_special_hint?>') {
            separator_hr.style.display = 'none';
            input_div.style.display = '';
            extra_div.style.display = 'none';
          }
          else {
            separator_hr.style.display = 'none';
            input_div.style.display = '';
            extra_div.style.display = '';
            if ([<?=self::js_(self::q_types_with_extra_singleline)?>].includes(select_type.value)) {
              extra_singleline_input.attr('data-visible', '1');
              extra_multiline_textarea.attr('data-visible', null);
              extra_multiline_textarea.val('');
              extra_singleline_input.each(on_input_text_show_hide_reuse_extra_checkbox);
            } else {
              extra_singleline_input.attr('data-visible', null);
              extra_multiline_textarea.attr('data-visible', '1');
              extra_singleline_input.val('');
              extra_multiline_textarea.each(on_input_extra_text_update_rows);
              extra_multiline_textarea.each(on_input_text_show_hide_reuse_extra_checkbox);
            }
            reuse_extra_check.each(on_check_reuse_extra_show_hide_extra_textbox_and_in_matrix_checkbox);
            in_matrix_check.each(on_check_in_matrix_show_hide_reuse_extra_checkbox);
          }
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
          var extra_singleline = select_type.parentNode.querySelector('[name^="q_extra_singleline_"]');
          var extra_multiline = select_type.parentNode.querySelector('[name^="q_extra_multiline_"]');

          if (checkbox_reuse_extra.checked) {
            extra_singleline.style.display = 'none';
            extra_multiline.style.display = 'none';
          }
          else {
            extra_singleline.style.display = extra_singleline.getAttribute('data-visible') ? '' : 'none';
            extra_multiline.style.display = extra_multiline.getAttribute('data-visible') ? '' : 'none';
          }

          var checkbox_in_matrix = select_type.parentNode.querySelector('[name^="q_in_matrix_"]');
          var label_checkbox_in_matrix = checkbox_in_matrix.parentNode;
          if (checkbox_reuse_extra.checked && [<?=self::js_(self::q_types_select)?>].includes(select_type.value)) {
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
          t_clone.querySelector('[name="q_required_"]').name += i;

          if (i > 0) {
            var last = question_divs.last()[0];
            t_clone.querySelector('[name^="q_type_"]').value = last.querySelector('[name^="q_type_"]').value;
            if (last.querySelector('[name^="q_reuse_extra_"]').checked) {
              t_clone.querySelector('[name^="q_reuse_extra_"]').checked = true;
              t_clone.querySelector('[name^="q_in_matrix_"]').checked = last.querySelector('[name^="q_in_matrix_"]').checked;
            }
            else {
              t_clone.querySelector('[name^="q_extra_singleline_"]').value = last.querySelector('[name^="q_extra_singleline_"]').value;
              t_clone.querySelector('[name^="q_extra_multiline_"]').value = last.querySelector('[name^="q_extra_multiline_"]').value;
            }
            t_clone.querySelector('[name^="q_required_"]').checked = last.querySelector('[name^="q_required_"]').checked;
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

    <h1><?=__('De-/Aktivieren', 'LL_survey')?></h1>

    <form method="post" action="admin-post.php">
      <p>Status: <code><?=$survey['active'] ? __('aktiv', 'LL_survey') : __('inaktiv', 'LL_survey')?></code></p>
      <p class="description">
        <?=__('Solange die Umfrage deaktiviert ist, können nur eingeloggte (WP-)Nutzer die Umfrage sehen und testen. Antworten werden nicht gespeichert.', 'LL_survey')?><br />
        <?=__('In aktiven Umfragen können Fragen nicht mehr neu hinzugefügt und existierende nur noch eingeschränkt bearbeitet werden.', 'LL_survey')?>
      </p>
      <input type="hidden" name="action" value="<?=self::_?>_survey_action" />
      <input type="hidden" name="survey_id" value="<?=$survey_id?>" />
      <input type="hidden" name="de_activate" value="<?=$survey['active'] ? '0' : '1'?>" />
      <?=wp_nonce_field(self::_ . '_survey_de_activate')?>
      <?php
      if ($survey['active']) {
        submit_button(__('Umfrage deaktivieren und bisherige Antworten löschen', 'LL_survey'), '', 'submit', true, 'onclick="return confirm(\'' . __('Gelöschte Antworten können nicht wiederhergestellt werden.\nUmfrage jetzt wirklich deaktivieren und bisherige Antworten löschen?', 'LL_survey') . '\')"');
      }
      else {
        submit_button(__('Umfrage aktivieren', 'LL_survey'), '');
      }
      ?>
    </form>

    <hr />

    <h1><?=__('Löschen', 'LL_survey')?></h1>

    <?php
    if ($survey['active']) {
      echo '<p class="description">' . __('Die Umfrage kann nicht gelöscht werden solange sie aktiv ist.', 'LL_survey') . '</p>';
    }
    else {
      ?>
      <form method="post" action="admin-post.php">
        <input type="hidden" name="action" value="<?=self::_?>_survey_action" />
        <?php wp_nonce_field(self::_ . '_survey_delete'); ?>
        <input type="hidden" name="survey_id" value="<?=$survey_id?>" />
        <?php submit_button(__('Umfrage löschen', 'LL_survey'), '', 'submit', true, 'onclick="return confirm(\'' . __('Gelöschte Umfragen können nicht wiederhergestellt werden.\nUmfrage jetzt wirklich endgültig löschen?', 'LL_survey') . '\')"'); ?>
      </form>
      <?php
    }
  }

  static function admin_display_survey_answers()
  {
    $survey_id = $_GET['answers'];
    $survey = self::db_get_survey_by_id($survey_id);
    if (empty($survey)) {
      self::message(sprintf(__('Umfrage <b>%d</b> existiert nicht.', 'LL_survey'), $survey_id));
      wp_redirect(self::admin_url() . self::admin_page_surveys);
      exit;
    }

    ?>
    <h1><?=__('Umfragen', 'LL_survey')?> &gt; #<?=$survey['id']?> <?=$survey['title']?> &gt; <?=__('Antworten', 'LL_survey')?></h1>

    <?php
    $answers = self::db_get_answers_by_survey($survey_id);
    if (empty($answers)) {
      echo '<p>' . __('Keine Antworten bisher.', 'LL_survey') . '</p>';
      return;
    }

    $questions = self::db_get_questions_by_survey_with_reuse_extra($survey_id);
    $questions = array_merge([['position' => -1, 'id' => 'time', 'text' => 'Zeit']], $questions);
    usort($questions, function(&$a, &$b) { return $a['position'] - $b['position']; });
    ?>
    <style>
      .<?=self::_?>_answers {
        width: 100%;
        max-width: 100%;
        overflow-x: scroll;
        margin-top: 10pt;
      }
      .<?=self::_?>_answers table {
        width: auto;
        min-width: 100%;
        border-left: none;
      }
      .<?=self::_?>_answers tr:first-child td {
        white-space: nowrap;
        word-wrap: normal;
        word-break: normal;
      }
      .<?=self::_?>_answers th {
        min-width: 100pt;
        max-width: 200pt;
        position: sticky;
        left: 0;
        border-right: 1px solid #ccd0d4;
        border-left: 1px solid #ccd0d4;
        background: white;
      }
      .<?=self::_?>_answers tr:nth-child(odd) th {
        background: #f9f9f9;
      }
    </style>
    <div class="<?=self::_?>_answers">
      <table class="widefat fixed striped">
        <?php
        foreach ($questions as &$question) {
          $q_id = $question['id'];
          if ($q_id !== 'time') {
            $q_id = 'q_' . $q_id;
          }
          ?>
          <tr>
            <th><?=$question['text']?></th>
            <?php
            foreach ($answers as &$answer) {
              ?>
              <td><?=$answer[$q_id]?></td>
              <?php
            }
            ?>
          </tr>
          <?php
        }
        ?>
      </table>
    </div>
    <?php

    echo '<pre>';
    var_dump($answers);
    echo '</pre>';

    echo '<pre>';
    var_dump($questions);
    echo '</pre>';
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
          'start' => $_POST['start'] ?: null,
          'end' => $_POST['end'] ?: null,
          'redirect_page' => $_POST['redirect_page'] ?: null]);
        self::message(__('Umfragedaten aktualisiert.', 'LL_survey'));

        $questions = [];
        $questions_deleted = 0;
        $questions_not_added = 0;
        $questions_updated = 0;
        $questions_added = 0;
        $separators_removed = 0;
        $i = 0;
        while (isset($_POST['q_id_' . $i])) {
          $question = [
            'id' => $_POST['q_id_' . $i] ?: null,
            'type' => $_POST['q_type_' . $i],
            'text' => null,
            'extra' => null,
            'reuse_extra' => null,
            'in_matrix' => null,
            'required' => null
          ];
          if ($question['type'] === self::q_type_special_delete) {
            if (!is_null($question['id'])) {
              self::db_delete_question($question['id']);
              ++$questions_deleted;
            }
            else
              ++$questions_not_added;
          }
          else {
            if ($question['type'] !== self::q_type_special_separator) {
              $question['text'] = trim($_POST['q_text_' . $i]);
              if ($question['type'] !== self::q_type_special_hint) {
                $can_have_singleline_extra = in_array($question['type'], self::q_types_with_extra_singleline);
                $can_have_multiline_extra = in_array($question['type'], self::q_types_with_extra_multiline);
                if ($can_have_singleline_extra) $question['extra'] = $_POST['q_extra_singleline_' . $i] ?: null;
                else if ($can_have_multiline_extra) $question['extra'] = $_POST['q_extra_multiline_' . $i] ?: null;
                $question['reuse_extra'] = ($can_have_singleline_extra || $can_have_multiline_extra) && is_null($question['extra']) && $_POST['q_reuse_extra_' . $i];
                $question['in_matrix'] = $question['reuse_extra'] && $_POST['q_in_matrix_' . $i];
                $question['required'] = !!$_POST['q_required_' . $i];
              }
            }
            $questions[intval($_POST['q_order_' . $i])] = $question;
            if (!is_null($question['id']))
              ++$questions_updated;
            else
              ++$questions_added;
          }
          ++$i;
        }

        ksort($questions);

        // remove double separators
        $last_was_separator = reset($questions)['type'] === self::q_type_special_separator;
        while (true) {
          $q = next($questions);
          if ($q === false) break;
          $q_is_separator = $q['type'] === self::q_type_special_separator;
          if ($last_was_separator && $q_is_separator) {
            unset($questions[key($questions)]);
            ++$separators_removed;
          }
          $last_was_separator = $q_is_separator;
        };
        // remove separators at begin and end
        if (reset($questions)['type'] === self::q_type_special_separator) {
          unset($questions[key($questions)]);
          ++$separators_removed;
        }
        if (end($questions)['type'] === self::q_type_special_separator) {
          unset($questions[key($questions)]);
          ++$separators_removed;
        }
        reset($questions);

        // add/update questions
        $previous_type = null;
        $previous_id = null;
        $i = 0;
        foreach ($questions as $question) {
          if ($previous_type !== $question['type']) {
            $previous_id = null;
          }
          if (is_null($question['id'])) {
            $question['id'] = self::db_add_question($survey_id, $i, $question['type'], $question['text'], $question['extra'], $question['reuse_extra'] ? $previous_id : null, $question['in_matrix'], $question['required']);
          }
          else {
            self::db_update_question($question['id'], $i, $question['type'], $question['text'], $question['extra'], $question['reuse_extra'] ? $previous_id : null, $question['in_matrix'], $question['required']);
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
        if ($separators_removed > 0) self::message(sprintf(__('%d Seitenwechsel entfernt', 'LL_survey'), $separators_removed));

        wp_redirect(self::admin_url() . self::admin_page_survey_edit . $survey_id);
        exit;
      }

      else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_survey_de_activate')) {
        if ($_POST['de_activate']) {
          self::db_create_survey_table($_POST['survey_id']);
        }
        else {
          $table_name = self::db_(self::table_answers . $_POST['survey_id']);
          self::_db_delete_table($table_name);
          self::message('Datenbank für Umfrage gelöscht: ' . $table_name);
        }
        self::db_update_survey($_POST['survey_id'], ['active' => $_POST['de_activate']]);
        self::message($_POST['de_activate'] ? __('Umfrage aktiviert.', 'LL_survey') : __('Umfrage deaktiviert.', 'LL_survey'));
        wp_redirect(self::admin_url() . self::admin_page_survey_edit . $_POST['survey_id']);
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

  static function db_create_survey_table($survey_id)
  {
    $questions = self::db_get_questions_by_survey($survey_id, ['id', 'type', 'extra']);
    $q_values = [];
    foreach ($questions as $q) {
      $sql_type = null;
      switch ($q['type']) {
        case self::q_type_text:
          switch ($q['extra']) {
            case 'number':
              $sql_type = 'int';
              break;
            case 'date':
              $sql_type = 'date';
              break;
            case 'time':
              $sql_type = 'time';
              break;
            case 'datetime-local':
              $sql_type = 'datetime';
              break;
            case 'email':
              $sql_type = 'varchar(200)';
              break;
            case 'url':
              $sql_type = 'varchar(1000)';
              break;
            default: // pattern
              $sql_type = 'text';
          }
          break;
        case self::q_type_select:
        case self::q_type_multiselect:
          $sql_type = 'text';
          break;
        case self::q_type_check:
          $sql_type = 'boolean';
          break;
        default: // separator or hint
      }
      if (!is_null($sql_type)) {
        $q_values[] = self::escape_key('q_' . $q['id']) . ' ' . $sql_type;
      }
    }

    global $wpdb;
    $table_name = self::db_(self::table_answers . $survey_id);
    $sql_query = '
      CREATE TABLE ' . self::escape_key($table_name) . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ' . implode(', ', $q_values) . ',
        PRIMARY KEY (`id`)
      ) ' . $wpdb->get_charset_collate() . ';';
    $r = $table_name . ' : ' . ($wpdb->query($sql_query) ? 'OK' : $wpdb->last_error . '<hr />' . $wpdb->last_query);

    self::message('Datenbank für Umfrage eingerichtet: ' . $r);
  }



  // surveys
  // - id
  // - name
  // - active
  // - start
  // - end
  // questions
  // - id
  // - survey
  // - position
  // - type
  // - text
  // - extra
  // - reuse_extra
  // - in_matrix
  // - required

  static function print_navigation_buttons($max_num_matrix_options, $back, $next, $submit) {
    ?> 
    <tr class="<?=self::_?>_next_page">
      <td colspan="<?=$max_num_matrix_options + 1?>" style="width: 100%;">
        <?php
        if ($back) {
          ?> 
          <input type="button" class="<?=self::_?>_btn_back" value="<?=__('Zurück', 'LL_survey')?>" />
          <?php
        }
        if ($next) {
          ?> 
          <input type="button" class="<?=self::_?>_btn_next" value="<?=__('Weiter', 'LL_survey')?>" />
          <?php
        }
        if ($submit) {
          ?> 
          <input type="submit" value="<?=__('Meine Antworten jetzt absenden', 'LL_survey')?>" disabled />
          <?php
        }
        ?> 
      </td>
    </tr>
    <?php
  }

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
      default:
    }

    ob_start();
    
    if ($_GET[self::_ . '_finished'] == $survey_id) {
      ?>
      <div class="<?=self::_?>_finished"><?=__('Umfrage abgeschlossen!', 'LL_survey')?></div>
      <script>
        url = '/' + window.location.href.substr(window.location.href.indexOf('/', 8) + 1);
        url = url.replace(/([?&])LL_survey_finished=\d+$/, '');
        window.history.pushState(null, "", url);
      </script>
      <?php
      return ob_get_clean();
    }

    if ($survey['active'] || is_user_logged_in()) {
      $questions = self::db_get_questions_by_survey($survey_id);
      $questions_by_id = [];
      $max_num_matrix_options = 1;
      foreach ($questions as $idx => &$question) {
        $question['is_first_matrix_row'] = !$question['in_matrix'] && count($questions) > ($idx + 1) && $questions[$idx + 1]['in_matrix'];
        $question['is_last_matrix_row'] = $question['in_matrix'] && count($questions) > ($idx + 1) && !$questions[$idx + 1]['in_matrix'];
        if (!is_null($question['extra']) && in_array($question['type'], self::q_types_with_extra_multiline)) {
          $question['extra'] = explode("\n", $question['extra']) ?: [];
          $max_num_matrix_options = max($max_num_matrix_options, count($question['extra']));
        }
        $questions_by_id[$question['id']] = $question;
      }
      ?> 
      <form method="post" action="<?=self::json_url()?>finish">
      <input type="hidden" name="survey_id" value="<?=$survey_id?>" />
      <div class="<?=self::_?>">
      <?php
      $matrix_input_row_style = '';//style="width: ' . (60 / $max_num_matrix_options) . '%;"';
      $without_separator = true;
      foreach ($questions as &$question) {
        $tag_id_value = 'q_' . $question['id'];
        $tag_name = 'name="' . $tag_id_value . '"';
        $tag_name_and_id = $tag_name . ' id="' . $tag_id_value . '"';
        $extra = ($question['reuse_extra'] ? $questions_by_id[$question['reuse_extra']] : $question)['extra'];
        $required = $question['required'] ? 'required' : '';
        $q_class = 'class="' . self::_ . '_question_div ' . self::_ . '_question_' . $question['type'] . ' ' . (($question['in_matrix'] || $question['is_first_matrix_row']) ? self::_ . '_question_matrix' : '') . '"';
        $q_type = 'data-question="' . $question['id'] . '" data-question-type="' . $question['type'] . '"';
        switch ($question['type']) {
          case self::q_type_special_separator:
            $back = true;
            if ($without_separator) {
              $without_separator = false;
              $back = false;
            }
            self::print_navigation_buttons($max_num_matrix_options, $back, true, false);
            ?> 
          </div>
          <div class="<?=self::_?>" style="display: none;">
            <?php
            break;

          case self::q_type_special_hint:
            ?> 
            <div <?=$q_class?>>
              <div class="<?=self::_?>_question"><?=$question['text']?></div>
            </div>
            <?php
            break;

          case self::q_type_text:
            ?> 
            <div <?=$q_class?> <?=$q_type?>>
              <div class="<?=self::_?>_question"><?=$question['text']?></div>
              <div class="<?=self::_?>_input">
                <?php
                if (in_array($extra, self::q_special_text_types)) {
                  ?> 
                  <input type="<?=$extra?>" <?=$tag_name_and_id?> class="text-input-field" <?=$required?> />
                  <?php
                }
                else if (preg_match(self::pattern_multiline[0], $extra)) {
                  $rows = preg_replace(self::pattern_multiline[0], self::pattern_multiline[1], $extra) ?: '3';
                  ?> 
                  <textarea <?=$tag_name_and_id?> rows="<?=$rows?>"></textarea>
                  <?php
                }
                else {
                  ?> 
                  <input type="text" <?=$extra ? 'pattern="' . $extra . '"' : ''?> <?=$tag_name_and_id?> <?=$required?> />
                  <?php
                }
                ?> 
              </div>
            </div>
            <?php
            break;

          case self::q_type_check:
            ?> 
            <div <?=$q_class?> <?=$q_type?>>
              <div class="<?=self::_?>_question"><?=$question['text']?></div>
              <div class="<?=self::_?>_input">
                <input type="checkbox" <?=$tag_name_and_id?> <?=$required?> /><label for="<?=$tag_id_value?>" data-on="<?=$extra[0] ?? __('Ja', 'LL_mailer')?>" data-off="<?=$extra[1] ?? __('Nein', 'LL_mailer')?>"></label>
              </div>
            </div>
            <?php
            break;

          case self::q_type_select:
            if ($question['in_matrix'] || $question['is_first_matrix_row']) {
              if ($question['is_first_matrix_row']) {
              ?> 
            <div <?=$q_class?>>
              <div class="<?=self::_?>_input_matrix_header_row">
                <div></div>
                <?php
                foreach ($extra as &$option) {
                  ?> 
                <div class="<?=self::_?>_input_matrix_header" <?=$matrix_input_row_style?>>
                  <span><?=$option?></span>
                </div>
                <?php
                }
              ?> 
              </div>
              <?php
              }
              ?> 
              <div class="<?=self::_?>_input_matrix_row" <?=$q_type?>>
                <div class="<?=self::_?>_question"><?=$question['text']?></div>
                <?php
                for ($idx = 0; $idx < count($extra); ++$idx) {
                  $tag_id_value_with_idx = $tag_id_value . '_' . $idx;
                  ?> 
                <div class="<?=self::_?>_input <?=self::_?>_input_matrix" <?=$matrix_input_row_style?>>
                  <input type="radio" <?=$tag_name?> value="<?=$idx?>" id="<?=$tag_id_value_with_idx?>" <?=$required?> /><label for="<?=$tag_id_value_with_idx?>"></label>
                </div>
                <?php
              }
              ?> 
              </div>
              <?php
              if ($question['is_last_matrix_row']) {
                ?> 
              <div class="<?=self::_?>_input_matrix_header_row">
                <div></div>
                <?php
                foreach ($extra as &$option) {
                  ?> 
                <div class="<?=self::_?>_input_matrix_header" <?=$matrix_input_row_style?>>
                  <span><?=$option?></span>
                </div>
                <?php
                }
              ?> 
              </div>
            </div>
                <?php
              }
            }
            else {
              ?> 
            <div <?=$q_class?> <?=$q_type?>>
              <div class="<?=self::_?>_question"><?=$question['text']?></div>
              <div class="<?=self::_?>_input">
              <?php
              foreach ($extra as $idx => &$option) {
                $tag_id_value_with_idx = $tag_id_value . '_' . $idx;
                ?> 
                <div><input type="radio" <?=$tag_name?> value="<?=$idx?>" id="<?=$tag_id_value_with_idx?>" <?=$required?> /><label for="<?=$tag_id_value_with_idx?>"><?=$option?></label></div>
                <?php
              }
              ?> 
              </div>
            </div>
              <?php
            }
            break;

          case self::q_type_multiselect:
            if ($question['in_matrix'] || $question['is_first_matrix_row']) {
              ?> 
              <?=$question['text']?> (noch nicht verfügbar)
              <?php
            }
            else {
              ?> 
            <div <?=$q_class?> <?=$q_type?>>
              <div class="<?=self::_?>_question"><?=$question['text']?></div>
              <div class="<?=self::_?>_input">
              <?php
              foreach ($extra as $idx => &$option) {
                $tag_id_value_with_idx = $tag_id_value . '_' . $idx;
                $tag_name_and_id_with_idx = 'name="' . $tag_id_value_with_idx . '" id="' . $tag_id_value_with_idx . '"';
                ?> 
                <div><input type="checkbox" <?=$tag_name_and_id_with_idx?> <?=$question['required'] ? 'data-multi-required="true"' : ''?> /><label for="<?=$tag_id_value_with_idx?>"><?=$option?></label></div>
                <?php
              }
              ?> 
              </div>
            </div>
              <?php
            }
            break;
        }
      }
      self::print_navigation_buttons($max_num_matrix_options, !$without_separator, true, false);
      ?> 
      </div>
      <div class="<?=self::_?>" style="display: none;">
        <?php
        self::print_navigation_buttons($max_num_matrix_options, true, false, true);
        ?> 
      </div>
      </form>
      <script>
        jQuery(function() {
          var form = document.querySelector('.<?=self::_?>').closest('form');
          var submit_button = form.querySelector('input[type="submit"]');
          jQuery('.<?=self::_?>_btn_back').click(function() {
            var current_table = this.closest('.<?=self::_?>');
            var next_table = current_table.previousElementSibling;
            jQuery(current_table).fadeOut(200, function () {
              submit_button.disabled = true;
              jQuery(next_table).fadeIn(200);
              jQuery('html, body').animate({ scrollTop: jQuery(next_table.querySelector('.<?=self::_?>_btn_next')).offset().top }, 'slow');
            });
          });
          jQuery('.<?=self::_?>_btn_next').click(function() {
            var current_table = this.closest('.<?=self::_?>');
            var valid = true;
            current_table.querySelectorAll('[data-question]').forEach(function(question_tag) {
              if (valid) {
                var input = null;
                var inputs = null;
                switch (question_tag.getAttribute('data-question-type')) {
                  case '<?=self::q_type_text?>':
                    input = question_tag.querySelector('input,textarea');
                    break;
                  case '<?=self::q_type_check?>':
                    input = question_tag.querySelector('input');
                    break;
                  case '<?=self::q_type_select?>':
                  case '<?=self::q_type_multiselect?>':
                    inputs = question_tag.querySelectorAll('input'); // ich liebe dich
                    if (question_tag.closest('.<?=self::_?>_question_div').className.split(/\s/).indexOf("<?=self::_?>_question_matrix") !== -1) {
                      input = inputs[Math.ceil(inputs.length / 2) - 1];
                    }
                    else {
                      input = inputs[inputs.length - 1];
                    }
                    break;
                }
                if (input.getAttribute('data-multi-required') !== null) {
                  var multi_valid = false;
                  inputs.forEach(function(i) {
                    multi_valid |= i.checked;
                  });
                  if (!multi_valid) {
                    valid = false;
                    inputs.forEach(function(i) {
                      i.required = true;
                    });
                    input.reportValidity();
                    var multiInvalidClick = function() {
                      inputs.forEach(function(i) {
                        i.removeEventListener('change', multiInvalidClick);
                        i.required = false;
                      });
                    };
                    inputs.forEach(function(i) { i.addEventListener('change', multiInvalidClick); });
                  }
                }
                else {
                  valid &= input.reportValidity();
                }
              }
            });
            if (valid) {
              var next_table = current_table.nextElementSibling;
              jQuery(current_table).fadeOut(200, function () {
                submit_button.disabled = !next_table.querySelector('input[type="submit"]');
                jQuery(next_table).fadeIn(200);
                jQuery('html, body').animate({ scrollTop: jQuery('h1').offset().top }, 'slow');

                window.addEventListener('beforeunload', function (e) {
                  e.preventDefault();
                  e.returnValue = '';
                });
              });
            }
          });
        });
      </script>
      <?php
//      print_r($survey);
//      print_r($questions);
    }
    return ob_get_clean();
  }



  // surveys
  // - id
  // - name
  // - active
  // - start
  // - end
  // questions
  // - id
  // - survey
  // - position
  // - type
  // - text
  // - extra
  // - reuse_extra
  // - in_matrix
  // - required
  // answers
  // - id
  // - question
  // - text
  // - time

  static function finish_survey($request)
  {
    if (isset($request['survey_id'])) {
      $survey_id = $request['survey_id'];
      $survey = self::db_get_survey_by_id($survey_id, ['redirect_page', 'active']);
      if ($survey['active']) {
        $questions = self::db_get_questions_by_survey_with_reuse_extra($survey_id);
        $answers = [];
        foreach ($questions as &$q) {
          $q_id = 'q_' . $q['id'];
          $q_extra = explode("\n", $q['extra'] ?: $q['indirect_extra']) ?: [];
          switch ($q['type']) {
            case self::q_type_special_separator:
            case self::q_type_special_hint:
              break;
            case self::q_type_check:
              $answers[$q_id] = isset($request[$q_id]);
              break;
            case self::q_type_multiselect:
              $tmp = [];
              for ($idx = 0; $idx < count($q_extra); ++$idx) {
                if (isset($request[$q_id . '_' . $idx])) {
                  $tmp[] = $idx;
                }
              }
              $answers[$q_id] = implode(',', $tmp);
              break;
            default:
              $answers[$q_id] = $request[$q_id];
          }
        }

        self::db_add_answer($survey_id, $answers);
      }
      if (!empty($survey['redirect_page'])) {
        wp_redirect(get_permalink(get_page_by_path($survey['redirect_page'])));
      }
      else {
        wp_redirect(add_query_arg(self::_ . '_finished', $survey_id, wp_get_referer()));
      }
      exit;
    }
    return '';
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
      register_rest_route(self::_ . '/v1', 'finish', [
        'callback' => self::_('finish_survey'),
        'methods' => 'POST'
      ]);
    });
  }
}

LL_survey::init_hooks_and_filters();

?>