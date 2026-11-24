<?php

class SupsysticTables_Widget extends WP_Widget
{
  private $tables;

  /**
   * Register widget with WordPress.
   */
  function __construct()
  {
    $this->tables = new SupsysticTables_Tables_Model_Tables();
    // id_base stays "tables_widget", so widgets already placed keep working (plugins/.claude/fix/shared/wp-widgets-check.md)
    parent::__construct('tables_widget', __('Data Tables by Supsystic', 'supsystic_tables'), [
      'description' => __('Shows one of your tables: a data table, a WooCommerce product table or a pricing table.', 'supsystic_tables'),
    ]);
  }

  /**
   * @return array id => label
   */
  private function getTableOptions()
  {
    return $this->tables->getOptionsForSelect();
  }

  /**
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget($args, $instance)
  {
    // No table picked: nothing at all (before, an empty box with the title)
    $tableId = isset($instance['id']) ? (int) $instance['id'] : 0;
    if ($tableId < 1) {
      return;
    }
    $title = apply_filters('widget_title', isset($instance['title']) ? $instance['title'] : '');
    echo wp_kses_post($args['before_widget']);
    if (!empty($title)) {
      echo wp_kses_post($args['before_title'] . $title . $args['after_title']);
    }
    echo do_shortcode('[supsystic-tables id=' . $tableId . ' position="center"]');
    echo wp_kses_post($args['after_widget']);
  }

  /**
   * Back-end widget fo*rm.
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   */
  public function form($instance)
  {
    // A new widget starts without a title (before: "Title", which then showed on the site); saved widgets keep theirs
    $title = isset($instance['title']) ? $instance['title'] : '';

    $options = $this->getTableOptions();

    if (!$options) {
      echo '<p>' . esc_html__('No tables yet.', 'supsystic_tables') . ' <a href="' . esc_url(admin_url('admin.php?page=supsystic-tables#add')) . '" target="_blank">' . esc_html__('Create a table in Data Tables', 'supsystic_tables') . '</a></p>';
    }
    ?>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('id')); ?>"><?php esc_html_e('Select table:', 'supsystic_tables'); ?></label>
            <select id="<?php echo esc_attr($this->get_field_id('id')); ?>" class="widefat" name="<?php echo esc_attr($this->get_field_name('id')); ?>">
                <option value=""><?php esc_html_e('— Select a table —', 'supsystic_tables'); ?></option>
                <?php foreach ($options as $id => $label) { ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected(!empty($instance['id']) ? (int) $instance['id'] : 0, $id); ?>><?php echo esc_html($label); ?></option>
                <?php } ?>
            </select>
        </p>
    <?php
  }

  /**
   * Sanitize widget form values as they are saved.
   *
   * @see WP_Widget::update()
   *
   * @param array $new_instance Values just sent to be saved.
   * @param array $old_instance Previously saved values from database.
   *
   * @return array Updated safe values to be saved.
   */
  public function update($new_instance, $old_instance)
  {
    $instance = [];
    $instance['title'] = !empty($new_instance['title']) ? strip_tags($new_instance['title']) : '';
    $instance['id'] = !empty($new_instance['id']) ? strip_tags($new_instance['id']) : '';
    return $instance;
  }
}
