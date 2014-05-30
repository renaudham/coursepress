<?php
global $action, $page;
global $page, $user_id, $coursepress_admin_notice;
global $coursepress;

$discussion_id = '';

if (isset($_GET['discussion_id'])) {
    $discussion = new Discussion($_GET['discussion_id']);
    $discussion_details = $discussion->get_discussion();
    $discussion_id = (int)$_GET['discussion_id'];
} else {
    $discussion = new Discussion();
    $discussion_id = 0;
}

wp_reset_vars(array('action', 'page'));

if (isset($_POST['action']) && ($_POST['action'] == 'add' || $_POST['action'] == 'update')) {

    check_admin_referer('discussion_details');

    $new_post_id = $discussion->update_discussion();

    if ($new_post_id !== 0) {
        ob_start();
        wp_redirect(admin_url('admin.php?page=' . $page . '&discussion_id=' . $new_post_id . '&action=edit'));
        exit;
    } else {
        //an error occured
    }
}

if (isset($_GET['discussion_id'])) {
    $meta_course_id = $discussion->details->course_id;
} else {
    $meta_course_id = '';
}
?>

<div class="wrap nosubsub">
    <div class="icon32" id="icon-themes"><br></div>

    <h2><?php _e('Discussion', 'cp'); ?><?php if (current_user_can('coursepress_create_discussion_cap')) { ?><a class="add-new-h2" href="<?php echo admin_url('admin.php?page=discussions&action=add_new');?>"><?php _e('Add New', 'cp'); ?></a><?php } ?></h2>

    <?php
    $message['ca'] = __('New Discussion added successfully!', 'cp');
    $message['cu'] = __('Discussion updated successfully.', 'cp');
    ?>

    <div class='wrap nocoursesub'>
        <form action='<?php echo admin_url('admin.php?page='.esc_attr($page).''.(($discussion_id !== 0) ? '&discussion_id=' . $discussion_id : '') . '&action=' . esc_attr($action). ($discussion_id !== 0) ? '&ms=cu' : '&ms=ca');?>' name='discussion-add' method='post'>

            <div class='course-liquid-left'>

                <div id='course-full'>

                    <?php wp_nonce_field('discussion_details'); ?>

                    <?php if (isset($discussion_id)) { ?>
                        <input type="hidden" name="discussion_id" value="<?php echo esc_attr($discussion_id); ?>" />
                        <input type="hidden" name="action" value="update" />
                    <?php } else { ?>
                        <input type="hidden" name="action" value="add" />
                    <?php } ?>

                    <div id='edit-sub' class='course-holder-wrap'>
                        <div class='course-holder'>
                            <div class='course-details'>
                                <label for='discussion_name'><?php _e('Discussion Title', 'cp'); ?></label>
                                <input class='wide' type='text' name='discussion_name' id='discussion_name' value='<?php
                                if (isset($_GET['discussion_id'])) {
                                    echo esc_attr(stripslashes($discussion->details->post_title));
                                }
                                ?>' />

                                <br/><br/>
                                <label for='course_name'><?php _e('Discussion Content', 'cp'); ?></label>
                                <?php
                                $args = array("textarea_name" => "discussion_description", "textarea_rows" => 10);
                                wp_editor(htmlspecialchars_decode(isset($discussion->details->post_content) ? $discussion->details->post_content : ''), "discussion_description", $args);
                                ?>
                                <br/>

                                <br clear="all" />
                                <br clear="all" />

                                <div class="full">
                                    <label><?php _e('Course', 'cp'); ?></label>
                                    <select name="meta_course_id">
                                        <?php
                                        $args = array(
                                            'post_type' => 'course',
                                            'post_status' => 'any',
                                            'posts_per_page' => -1
                                        );

                                        $courses = get_posts($args);

                                        foreach ($courses as $course) {
                                            ?>
                                            <option value="<?php echo $course->ID; ?>" <?php selected($meta_course_id, $course->ID); ?>><?php echo $course->post_title; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>

                                </div>


                                <div class="buttons">
                                    <?php
                                    if (($discussion_id == 0 && current_user_can('coursepress_create_discussion_cap')) || ($discussion_id != 0 && current_user_can('coursepress_update_discussion_cap')) || ($discussion_id != 0 && current_user_can('coursepress_update_my_discussion_cap') && $discussion_details->post_author == get_current_user_id())) {//do not show anything
                                        ?>
                                        <input type="submit" value = "<?php ($discussion_id == 0 ? _e('Create', 'cp') : _e('Update', 'cp')); ?>" class = "button-primary" />
                                        <?php
                                    } else {
                                        _e('You do not have required permissions for this action');
                                    }
                                    ?>
                                </div>

                                <br clear="all" />

                            </div>

                        </div>
                    </div>

                </div>
            </div> <!-- course-liquid-left -->
        </form>

    </div> <!-- wrap -->
</div>
