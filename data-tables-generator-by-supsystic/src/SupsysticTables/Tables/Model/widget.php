<?php

class SupsysticTables_Widget extends WP_Widget {

    private $tables;

    /**
     * Register widget with WordPress.
     */
    function __construct() {

        $this->tables = new SupsysticTables_Tables_Model_Tables();
        parent::__construct(
            'tables_widget',
            'Tables Widget',
            array( 'description' => 'Tables Widget' )
        );

    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget( $args, $instance ) {
        $title = apply_filters( 'widget_title', $instance['title'] );
        echo wp_kses_post($args['before_widget']);
        if ( ! empty( $title ) ) {
            echo wp_kses_post($args['before_title'] . $title . $args['after_title']);
        }
        echo wp_kses_post(do_shortcode('[supsystic-tables id=' . esc_html($instance['id']) . ' position="center"]'));
        echo wp_kses_post($args['after_widget']);
    }

    /**
     * Back-end widget fo*rm.
     * @see WP_Widget::form()
     *
     * @param array $instance Previously saved values from database.
     */
    public function form( $instance ) {
        if ( isset( $instance[ 'title' ] ) ) {
            $title = $instance[ 'title' ];
        } else {
            $title = 'Title';
        }

        $idTables = array();

        if ($this->tables->getList()) {
            foreach($this->tables->getList() as $table) {
                if (!empty($table->title) && isset($table->title)) {
                    array_push($idTables, array(
                        'name' => $table->title,
                        'value' => $table->id
                    ));
                }
            }
        }
        else {
            echo "<h4 style='color:red;text-align: center;'>No tables</h4>";
            echo wp_kses_post("<a href='".DTGS_PLUGIN_ADMIN_URL."?page=supsystic-tables'>Create table</a>");
        }

        ?>

        <p>
            <label for="<?php esc_attr_e($this->get_field_id( 'title' )); ?>"><?php esc_html_e( 'Title:' ); ?></label>
            <input class="widefat" id="<?php esc_attr_e($this->get_field_id( 'title' )); ?>" name="<?php esc_attr_e($this->get_field_name( 'title' )); ?>" type="text" value="<?php esc_attr_e( $title ); ?>">
            <label for="<?php esc_attr_e($this->get_field_id( 'id' )); ?>"><?php esc_html_e( 'Select table: ' ); ?></label>
            <select id="<?php esc_attr_e($this->get_field_id( 'id' )); ?>" class="widefat" name="<?php esc_attr_e($this->get_field_name( 'id' )); ?>" type="text">
                <?php foreach($idTables as $element)
                if (!empty($instance['id']) && $instance['id'] == $element['value'] && isset($element['value'])) {
                    esc_html_e("<option value=" . esc_attr($element['value']) . " selected>" . esc_attr($element['name']) . "</option>");
                } else {
                    esc_html_e("<option value=" . esc_attr($element['value']) . ">" . esc_attr($element['name']) . "</option>");
                }
                ?>
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
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        $instance['id'] = ( ! empty( $new_instance['id'] ) ) ? strip_tags( $new_instance['id'] ) : '';
        return $instance;
    }
}
