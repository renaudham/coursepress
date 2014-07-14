<?php
global $page, $user_id, $coursepress_admin_notice;
global $coursepress_modules, $coursepress_modules_labels, $coursepress_modules_descriptions, $coursepress_modules_ordered, $save_elements;

$course_id = '';

if (isset($_GET['course_id']) && is_numeric($_GET['course_id'])) {
    $course_id = (int) $_GET['course_id'];
    $course = new Course($course_id);
	
}

if (!current_user_can('coursepress_view_all_units_cap') && $course->details->post_author != get_current_user_id()) {
    die(__('You do not have required persmissions to access this page.', 'cp'));
}

if (!isset($_POST['force_current_unit_completion'])) {
    $_POST['force_current_unit_completion'] = 'off';
}

if (isset($_GET['unit_id'])) {
    $unit = new Unit($_GET['unit_id']);
    $unit_details = $unit->get_unit();
    $unit_id = (int) $_GET['unit_id'];
    $force_current_unit_completion = $unit->details->force_current_unit_completion;
} else {
    $unit = new Unit();
    $unit_id = 0;
    $force_current_unit_completion = 'off';
}

if (isset($_POST['action']) && ( $_POST['action'] == 'add_unit' || $_POST['action'] == 'update_unit' )) {

    if (wp_verify_nonce($_REQUEST['_wpnonce'], 'unit_details_overview_' . $user_id)) {

        //if ( ( $_POST['action'] == 'add_unit' && current_user_can( 'coursepress_create_course_unit_cap' ) ) || ( $_POST['action'] == 'update_unit' && current_user_can( 'coursepress_update_course_unit_cap' ) ) || ( $unit_id != 0 && current_user_can( 'coursepress_update_my_course_unit_cap' ) && $unit_details->post_author == get_current_user_id() ) ) {

        $new_post_id = $unit->update_unit(isset($_POST['unit_id']) ? $_POST['unit_id'] : 0 );

        if (isset($_POST['unit_state'])) {
            /* Save & Publish */
            $unit = new Unit($new_post_id);
            $unit->change_status($_POST['unit_state']);
        }

        if ($new_post_id != 0) {
            ob_start();
            if (isset($_GET['ms'])) {
                wp_redirect(admin_url('admin.php?page=' . $page . '&tab=units&course_id=' . $course_id . '&action=edit&unit_id=' . $new_post_id . '&ms=' . $_GET['ms']));
                //exit;
            } else {
                wp_redirect(admin_url('admin.php?page=' . $page . '&tab=units&course_id=' . $course_id . '&action=edit&unit_id=' . $new_post_id));
                //exit;
            }
        } else {
            //an error occured
        }

        /* }else{
          die( __( 'You don\'t have right permissions for the requested action', 'cp' ) );
          } */
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['new_status']) && isset($_GET['unit_id']) && is_numeric($_GET['unit_id'])) {
    $unit = new Unit($_GET['unit_id']);
    $unit_object = $unit->get_unit();
    if (( current_user_can('coursepress_change_course_unit_status_cap') ) || ( current_user_can('coursepress_change_my_course_unit_status_cap') && $unit_object->post_author == get_current_user_id() )) {
        $unit->change_status($_GET['new_status']);
    }
}
?>

<div class='wrap mp-wrap nocoursesub'>

    <div id="undefined-sticky-wrapper" class="sticky-wrapper">
        <div class="sticky-slider visible-small visible-extra-small"><i class="fa fa-chevron-circle-right"></i></div>
        <ul id="sortable-units" class="mp-tabs" style="">
            <?php
            $units = $course->get_units();
			?>
			<input type="hidden" name="unit_count" value="<?php echo $units ? count( $units ) : 0; ?>">
			<?php
            $list_order = 1;

            foreach ($units as $unit) {

                $unit_object = new Unit($unit->ID);
                $unit_object = $unit_object->get_unit();
                ?>
                <li class="mp-tab <?php echo ( isset($_GET['unit_id']) && $unit->ID == $_GET['unit_id'] ? 'active' : '' ); ?>">
                    <a class="mp-tab-link" href="<?php echo admin_url('admin.php?page=course_details&tab=units&course_id=' . $course_id . '&unit_id=' . $unit_object->ID . '&action=edit'); ?>"><?php echo $unit_object->post_title; ?></a>
                    <i class="fa fa-arrows-v cp-move-icon"></i>
                    <span class="unit-state-circle <?php echo (isset($unit_object->post_status) && $unit_object->post_status == 'publish' ? 'active' : ''); ?>"></span>

                    <input type="hidden" class="unit_order" value="<?php echo $list_order; ?>" name="unit_order_<?php echo $unit_object->ID; ?>" />
                    <input type="hidden" name="unit_id" class="unit_id" value="<?php echo $unit_object->ID; ?>" />                                                                                         
                </li>
                <?php
                $list_order++;
            }
            ?>
            <?php if (current_user_can('coursepress_create_course_unit_cap')) { ?>
                <li class="mp-tab <?php echo (!isset($_GET['unit_id']) ? 'active' : '' ); ?> static">
                    <a href="<?php echo admin_url('admin.php?page=course_details&tab=units&course_id=' . $course_id . '&action=add_new_unit'); ?>" class="<?php echo (!isset($_GET['unit_id']) ? 'mp-tab-link' : 'button-secondary' ); ?>"><?php _e('Add new Unit', 'cp'); ?></a>
                </li>
            <?php } ?>
        </ul>

        <?php if (current_user_can('coursepress_create_course_unit_cap')) { ?>
            <!--<div class="mp-tabs">
                <div class="mp-tab <?php echo (!isset($_GET['unit_id']) ? 'active' : '' ); ?>">
                    <a href="?page=course_details&tab=units&course_id=<?php echo $course_id; ?>&action=add_new_unit" class="<?php echo (!isset($_GET['unit_id']) ? 'mp-tab-link' : 'button-secondary' ); ?>"><?php _e('Add new Unit', 'cp'); ?></a>
                </div>
            </div>-->
        <?php } ?>

    </div>
    <div class='mp-settings'><!--course-liquid-left-->
        <?php
        //        $fragment = cp_get_fragment();
        ?>
        <form action="<?php echo esc_attr(admin_url('admin.php?page=' . $page . '&tab=units&course_id=' . $course_id . '&action=add_new_unit' . ( ( $unit_id !== 0 ) ? '&ms=uu' : '&ms=ua' ))); ?>#unit-page-<?php echo ( isset($fragment) && $fragment !== '' ? $fragment : '1' ); ?>" name="unit-add" id="unit-add" class="unit-add" method="post">

            <?php wp_nonce_field('unit_details_overview_' . $user_id); ?>
            <input type="hidden" name="unit_state" id="unit_state" value="<?php echo esc_attr((isset($unit_id) ? $unit_object->post_status : 'draft')); ?>" />
            <?php if (isset($unit_id)) { ?>

                <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>" />
                <input type="hidden" name="unit_id" value="<?php echo esc_attr($unit_id); ?>" />
                <input type="hidden" name="action" value="update_unit" />
            <?php } else { ?>
                <input type="hidden" name="action" value="add_unit" />
            <?php } ?>

            <?php
            $unit = new Unit($unit_id);
            $unit_object = $unit->get_unit();
            ?>

            <div class='section static'>
                <div class='unit-detail-settings'>
                    <h3><i class="fa fa-cog"></i> <?php _e('Unit Settings', 'cp'); ?>
                        <div class="unit-state">
                            <div class="unit_state_id" data-id="<?php echo (isset($unit_object->ID) && $unit_object->ID !== '') ? $unit_object->ID : '';?>"></div>
                            <span class="draft <?php echo ( $unit_object->post_status == 'unpublished' ) ? 'on' : '' ?>"><?php _e('Draft', 'cp'); ?></span>
                            <div class="control <?php echo ( $unit_object->post_status == 'unpublished' ) ? '' : 'on' ?>">
                                <div class="toggle"></div>
                            </div>
                            <span class="live <?php echo ( $unit_object->post_status == 'unpublished' ) ? '' : 'on' ?>"><?php _e('Live', 'cp'); ?></span>
                        </div>
                    </h3>

                    <div class='mp-settings-label'><label for='unit_name'><?php _e('Unit Title', 'cp'); ?></label></div>
                    <div class='mp-settings-field'>
                        <input class='wide' type='text' name='unit_name' id='unit_name' value='<?php echo esc_attr(stripslashes(isset($unit_details->post_title) ? $unit_details->post_title : '' )); ?>' />					
                    </div>
                    <div class='mp-settings-label'><label for='unit_availability'><?php _e('Unit Availability', 'cp'); ?></label></div>
                    <div class='mp-settings-field'>
                        <input type="text" class="dateinput" name="unit_availability" value="<?php echo esc_attr(stripslashes(isset($unit_details->unit_availability) ? $unit_details->unit_availability : ( date('Y-m-d', current_time('timestamp', 0)) ) )); ?>" />
                        <div class="force_unit_completion">
                            <input type="checkbox" name="force_current_unit_completion" id="force_current_unit_completion" value="on" <?php echo ( $force_current_unit_completion == 'on' ) ? 'checked' : ''; ?> /> <?php _e('User needs to complete current unit in order to access the next one', 'cp'); ?>
                        </div>						
                    </div>					
                </div>
                <div class="unit-control-buttons">

                    <?php
                    if (( $unit_id == 0 && current_user_can('coursepress_create_course_unit_cap'))) {//do not show anything
                        ?>
                        <input type="submit" name="submit-unit" class="button button-units save-unit-button" value="<?php _e('Save', 'cp'); ?>">
                        <!--<input type="submit" name="submit-unit-publish" class="button button-units button-publish" value="<?php _e('Publish', 'cp'); ?>">-->

                    <?php } ?>

                    <?php
                    if (( $unit_id != 0 && current_user_can('coursepress_update_course_unit_cap') ) || ( $unit_id != 0 && current_user_can('coursepress_update_my_course_unit_cap') && $unit_object->post_author == get_current_user_id() )) {//do not show anything
                        ?>
                        <input type="submit" name="submit-unit" class="button button-units save-unit-button" value="<?php echo ( $unit_object->post_status == 'unpublished' ) ? __('Save', 'cp') : __('Save', 'cp'); ?>">
                    <?php } ?>

                    <?php
                    if (( $unit_id != 0 && current_user_can('coursepress_update_course_unit_cap') ) || ( $unit_id != 0 && current_user_can('coursepress_update_my_course_unit_cap') && $unit_object->post_author == get_current_user_id() )) {//do not show anything
                        ?>
                        <a class="button button-preview" href="<?php echo get_permalink($unit_id); ?>" target="_new"><?php _e('Preview', 'cp'); ?></a>

                        <?php
                        /* if (current_user_can('coursepress_change_course_unit_status_cap') || ( current_user_can('coursepress_change_my_course_unit_status_cap') && $unit_object->post_author == get_current_user_id() )) { ?>
                          <input type="submit" name="submit-unit-<?php echo ( $unit_object->post_status == 'unpublished' ) ? 'publish' : 'unpublish'; ?>" class="button button-units button-<?php echo ( $unit_object->post_status == 'unpublished' ) ? 'publish' : 'unpublish'; ?>" value="<?php echo ( $unit_object->post_status == 'unpublished' ) ? __('Publish', 'cp') : __('Unpublish', 'cp'); ?>">
                          <?php
                          } */
                    }
                    ?>

                    <?php if ($unit_id != 0) { ?>
                        <span class="delete_unit">							
                            <a class="button button-units button-delete-unit" href="<?php echo admin_url('admin.php?page=course_details&tab=units&course_id=' . $course_id . '&unit_id=' . $unit_id . '&action=delete_unit'); ?>" onclick="return removeUnit();">
                                <i class="fa fa-trash-o"></i> <?php _e('Delete Unit', 'cp'); ?>
                            </a>
                        </span>
                    <?php } ?>

                </div>
            </div>
            <div class='section elements-section'>
                <input type="hidden" name="beingdragged" id="beingdragged" value="" />
                <div id='course'>


                    <div id='edit-sub' class='course-holder-wrap elements-wrap'>

                        <div class='course-holder'>
                            <!--<div class='course-details'>

                                <label for='unit_description'><?php _e('Introduction to this Unit', 'cp'); ?></label>
                            <?php
                            $args = array("textarea_name" => "unit_description", "textarea_rows" => 10);

                            if (!isset($unit_details->post_content)) {
                                $unit_details = new StdClass;
                                $unit_details->post_content = '';
                            }

                            $desc = '';
                            wp_editor(htmlspecialchars_decode($unit_details->post_content), "unit_description", $args);
                            ?>
                                <br/>

                            </div>-->


                            <div class="module-droppable levels-sortable ui-droppable" style='display: none;'>
                                <?php _e('Drag & Drop unit elements here', 'cp'); ?>
                            </div>

                            <div id="unit-pages">
                                <ul class="sidebar-name unit-pages-navigation">
                                    <li class="unit-pages-title"><span><?php _e('Unit Page(s)', 'cp'); ?></span></li>
                                    <?php
                                    $unit_pages = coursepress_unit_pages($unit_id);
                                    if ($unit_id == 0) {
                                        $unit_pages = 1;
                                    }
                                    for ($i = 1; $i <= $unit_pages; $i++) {
                                        ?>
                                        <li><a href="#unit-page-<?php echo $i; ?>"><?php echo $i; ?></a><span class="arrow-down"></span></li>
                                    <?php } ?>
                                    <li class="ui-state-default ui-corner-top"><a id="add_new_unit_page" class="ui-tabs-anchor">+</a></li>
                                </ul>

                                <?php
                                //$pages_num = 1;

                                $save_elements = true;

                                $module = new Unit_Module();
                                $modules = $module->get_modules($unit_id == 0 ? -1 : $unit_id );

                                for ($i = 1; $i <= $unit_pages; $i++) {
                                    ?>
                                    <div id="unit-page-<?php echo $i; ?>">
                                        <div class='course-details elements-holder'>
                                            <div class="unit_page_title">
                                                <label><?php _e('Page Title', 'cp'); ?>
                                                    <span class="delete_unit_page">							
                                                        <a class="button button-units button-delete-unit"><i class="fa fa-trash-o"></i> <?php _e('Delete Unit Page and Elements', 'cp'); ?></a>
                                                    </span>
                                                </label>
                                                <div class="description"><?php _e('The title will be displayed on the Course Overview and Unit page'); ?></div>
                                                <input type="text" value="<?php echo esc_attr($unit->get_unit_page_name($i)); ?>" name="page_title[]" class="page_title" />

                                                <label><?php _e('Build Page', 'cp'); ?></label>
                                                <div class="description"><?php _e('Click to add elements to the page'); ?></div>
                                            </div>
                                            <?php
                                            foreach ($coursepress_modules_ordered['output'] as $element) {
                                                ?>
                                                <div class="output-element <?php echo $element; ?>">
                                                    <span class="element-label">
                                                        <?php
                                                        $module = new $element;
                                                        echo $module->label;
                                                        ?>
                                                    </span>
                                                    <a class="add-element" id="<?php echo $element; ?>"></a>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                            <div class="elements-separator"></div>
                                            <?php
                                            foreach ($coursepress_modules_ordered['input'] as $element) {
                                                ?>
                                                <div class="input-element <?php echo $element; ?>">
                                                    <span class="element-label">
                                                        <?php
                                                        $module = new $element;
                                                        echo $module->label;
                                                        ?>
                                                    </span>
                                                    <a class="add-element" id="<?php echo $element; ?>"></a>
                                                </div>
                                                <?php
                                            }
                                            foreach ($coursepress_modules_ordered['invisible'] as $element) {
                                                ?>
                                                <div class="input-element <?php echo $element; ?>">
                                                    <span class="element-label">
                                                        <?php
                                                        $module = new $element;
                                                        echo $module->label;
                                                        ?>
                                                    </span>
                                                    <a class="add-element" id="<?php echo $element; ?>"></a>
                                                </div>
                                                <?php
                                            }
                                            $save_elements = false;
                                            ?>

                                            <hr />

                                            <span class="no-elements"><?php _e('No elements have been added to this page yet'); ?></span>

                                        </div>


                                        <?php /* if ( is_array( $modules ) && count( $modules ) >= 1 ) {
                                          ?>
                                          <div class="loading_elements"><?php _e( 'Loading Unit elements, please wait...', 'cp' ); ?></div>
                                          <?php } */ ?>

                                        <div class="modules_accordion">
                                            <!--modules will appear here-->
                                            <?php
                                            $pages_num = 1;
                                            foreach ($modules as $mod) {
                                                $class_name = $mod->module_type;

                                                if (class_exists($class_name)) {
                                                    $module = new $class_name();

                                                    if ($module->name == 'page_break_module') {
                                                        //echo 'page break at tab '.$i.'!<br />';
                                                        $pages_num++;
                                                        if ($pages_num == $i) {
                                                            $module->admin_main($mod);
                                                        }
                                                    } else {
                                                        //echo 'i:'.$i.', pages_num:'.$pages_num.'<br />';
                                                        if ($pages_num == $i) {
                                                            $module->admin_main($mod);
                                                            //print_r( $mod );
                                                        }
                                                    }
                                                }
                                            }
                                            //$module->get_modules_admin_forms( isset( $_GET['unit_id'] ) ? $_GET['unit_id'] : '-1' );
                                            ?>
                                        </div>

                                    </div>
                                    <?php
                                }
                                ?>
                            </div>

                            <div class="course-details-unit-controls">
                                <div class="unit-control-buttons">

                                    <?php
                                    if (( $unit_id == 0 && current_user_can('coursepress_create_course_unit_cap'))) {//do not show anything
                                        ?>
                                        <input type="submit" name="submit-unit" class="button button-units save-unit-button" value="<?php _e('Save', 'cp'); ?>">
                                        <!--<input type="submit" name="submit-unit-publish" class="button button-units button-publish" value="<?php _e('Publish', 'cp'); ?>">-->

                                    <?php } ?>

                                    <?php
                                    if (( $unit_id != 0 && current_user_can('coursepress_update_course_unit_cap') ) || ( $unit_id != 0 && current_user_can('coursepress_update_my_course_unit_cap') && $unit_object->post_author == get_current_user_id() )) {//do not show anything
                                        ?>
                                        <input type="submit" name="submit-unit" class="button button-units save-unit-button" value="<?php echo ( $unit_object->post_status == 'unpublished' ) ? __('Save', 'cp') : __('Save', 'cp'); ?>">
                                    <?php } ?>

                                    <?php
                                    if (( $unit_id != 0 && current_user_can('coursepress_update_course_unit_cap') ) || ( $unit_id != 0 && current_user_can('coursepress_update_my_course_unit_cap') && $unit_object->post_author == get_current_user_id() )) {//do not show anything
                                        ?>
                                        <a class="button button-preview" href="<?php echo get_permalink($unit_id); ?>" target="_new"><?php _e('Preview', 'cp'); ?></a>

                                        <?php
                                        /* if (current_user_can('coursepress_change_course_unit_status_cap') || ( current_user_can('coursepress_change_my_course_unit_status_cap') && $unit_object->post_author == get_current_user_id() )) { ?>
                                          <input type="submit" name="submit-unit-<?php echo ( $unit_object->post_status == 'unpublished' ) ? 'publish' : 'unpublish'; ?>" class="button button-units button-<?php echo ( $unit_object->post_status == 'unpublished' ) ? 'publish' : 'unpublish'; ?>" value="<?php echo ( $unit_object->post_status == 'unpublished' ) ? __('Publish', 'cp') : __('Unpublish', 'cp'); ?>">
                                          <?php
                                          } */
                                    }
                                    ?>

                                    <div class="unit-state">
                                        <div class="unit_state_id" data-id="<?php echo (isset($unit_object->ID) && $unit_object->ID !== '') ? $unit_object->ID : '';?>"></div>
                                        <span class="draft <?php echo ( $unit_object->post_status == 'unpublished' ) ? 'on' : '' ?>"><?php _e('Draft', 'cp'); ?></span>
                                        <div class="control <?php echo ( $unit_object->post_status == 'unpublished' ) ? '' : 'on' ?>">
                                            <div class="toggle"></div>
                                        </div>
                                        <span class="live <?php echo ( $unit_object->post_status == 'unpublished' ) ? '' : 'on' ?>"><?php _e('Live', 'cp'); ?></span>
                                    </div>
                                </div>
                            </div>

                        </div><!--/course-holder-->
                    </div><!--/course-holder-wrap-->
                </div><!--/course-->
            </div> <!-- /section -->
        </form>			
    </div> <!-- course-liquid-left -->

    <div class='level-liquid-right' style="display:none;">
        <div class="level-holder-wrap">
            <?php
            $sections = array("input" => __('Input Elements', 'cp'), "output" => __('Output Elements', 'cp'), "invisible" => __('Invisible Elements', 'cp'));

            foreach ($sections as $key => $section) {
                ?>

                <div class="sidebar-name no-movecursor">
                    <h3><?php echo $section; ?></h3>
                </div>

                <div class="section-holder" id="sidebar-<?php echo $key; ?>" style="min-height: 98px;">
                    <ul class='modules'>
                        <?php
                        if (isset($coursepress_modules[$key])) {
                            foreach ($coursepress_modules[$key] as $mmodule => $mclass) {
                                $module = new $mclass();
                                if (!array_key_exists($mmodule, $module)) {
                                    $module->admin_sidebar(false);
                                } else {
                                    $module->admin_sidebar(true);
                                }

                                $module->admin_main(array());
                            }
                        }
                        ?>
                    </ul>
                </div>
                <?php
            }
            ?>
        </div> <!-- level-holder-wrap -->

    </div> <!-- level-liquid-right -->


    <script type="text/javascript">
        jQuery(document).ready(function() {
            //coursepress_no_elements();
            jQuery('.modules_accordion .switch-tmce').each(function() {
                jQuery(this).trigger('click');
            });
            var current_page = jQuery('#unit-pages .ui-tabs-nav .ui-state-active a').html();
            var elements_count = jQuery('#unit-page-1 .modules_accordion .module-holder-title').length;
            //jQuery('#unit-page-' + current_unit_page + ' .elements-holder .no-elements').show();

            if ((current_page == 1 && elements_count == 0) || (current_page >= 2 && elements_count == 1)) {
                jQuery('#unit-page-' + current_page + ' .elements-holder .no-elements').show();
            } else {
                jQuery('#unit-page-' + current_page + ' .elements-holder .no-elements').hide();
            }
        });
    </script>
</div> <!-- wrap -->