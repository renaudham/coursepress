<?php
/**
 * Course functions and definitions.
 *
 * @since 3.0
 * @package CoursePress
 */

/**
 * Get course data object.
 *
 * @param int|WP_Post $course_id
 * @param boolean $cached Whether or not use cached course object
 *
 * @return WP_Error|CoursePress_Course
 */
function coursepress_get_course( $course_id = 0, $cached = true ) {
	global $coursepress_course, $coursepress_core;
	if ( empty( $course_id ) ) {
		// Assume current course
		if ( $coursepress_course instanceof CoursePress_Course ) {
			return $coursepress_course;
		} else {
			// Try current post
			$course_id = get_the_ID();
		}
	}
	if ( $course_id instanceof WP_Post ) {
		$course_id = $course_id->ID;
	}
	if (
		$coursepress_course instanceof CoursePress_Course
		&& $course_id == $coursepress_course->__get( 'ID' )
	) {
		return $coursepress_course;
	}
	if ( $cached && isset( $coursepress_core->courses[ $course_id ] ) ) {
		return $coursepress_core->courses[ $course_id ];
	}
	$course = new CoursePress_Course( $course_id );
	if ( ! $course->__get( 'is_error' ) ) {
		$coursepress_core->courses[ $course_id ] = $course;
		return $course;
	}
	return $course->wp_error();
}

/**
 * Get current course id.
 *
 * @param int|object Course object or id.
 *
 * @return int|bool
 */
function coursepress_get_course_id( $course = 0 ) {

	$course = coursepress_get_course( $course );

	if ( ! is_wp_error( $course ) ) {
		return $course->__get( 'ID' );
	}

	return false;
}

/**
 * Returns list courses.
 *
 * @param array $args  Arguments to pass to WP_Query.
 * @param int   $count This is not the count of resulted courses. This is the count
 *                     of total available courses without applying pagination limit.
 *                     This parameter does not expect incoming value. Total count will
 *                     be passed as reference, since this functions return value is an
 *                     array of course post objects.
 *
 * @return array Returns an array of courses where each course is an instance of CoursePress_Course object.
 */
function coursepress_get_courses( $args = array(), &$count = 0 ) {
	/** @var $coursepress_core CoursePress_Core */
	global $coursepress_core;

	// If courses per page is not set.
	if ( ! isset( $args['posts_per_page'] ) ) {
		// Set the courses per page from screen options.
		$posts_per_page = get_user_meta( get_current_user_id(), 'coursepress_course_per_page', true );
		// If screen option is not sert, default posts per page.
		if ( empty( $posts_per_page ) ) {
			$posts_per_page = coursepress_get_option( 'posts_per_page', 20 );
		}
		$args['posts_per_page'] = $posts_per_page;
		// Set the current page (get_query_var() won't work).
		$args['paged'] = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
	}

	// If search query found.
	if ( isset( $_GET['s'] ) ) {
		$args['s'] = $_GET['s'];
	}

	// If instructor id found, filter by instructor.
	if ( ! empty( $_GET['instructor_id'] ) ) {
		$instructor_filter = array(
			'key' => 'instructor',
			'value' => (int) $_GET['instructor_id'],
		);
		// If another meta query exists, add AND relation.
		if ( isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				$args['meta_query'],
				$instructor_filter,
			);
		} else {
			$args['meta_query'][] = $instructor_filter;
		}
	}

	// If filtered by category.
	if ( ! empty( $_GET['course_category'] ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'course_category',
				'field' => 'term_id',
				'terms' => intval( $_GET['course_category'] ),
			),
		);
	}

	$args = wp_parse_args( array(
		'post_type' => $coursepress_core->__get( 'course_post_type' ),
		'suppress_filters' => true,
		'fields' => 'ids',
		'orderby' => 'post_title',
		'order' => 'ASC',
	), $args );

	//$order_by = coursepress_get_setting( 'course/order_by', 'post_date' );
	//$order = coursepress_get_setting( 'course/order_by_direction', 'ASC' );

	// @todo: Apply orderby setting

	/**
	 * Filter course results.
	 *
	 * @since 3.0
	 * @param array $args
	 */
	$args = apply_filters( 'coursepress_pre_get_courses', $args );

	// Note: We need to use WP_Query to get total count.
	$query = new WP_Query();
	$results = $query->query( $args );
	// Update the total courses count (ignoring items per page).
	$count = $query->found_posts;

	$courses = array();

	if ( ! empty( $results ) ) {
		foreach ( $results as $result ) {
			$courses[ $result ] = coursepress_get_course( $result );
		}
	}

	return $courses;
}

function coursepress_get_post_statuses( $type, $current_status, $slug ) {
	$cp_type = get_cp_type( $type );
	$post_type = ! empty( $cp_type ) ? $cp_type : $type;
	$count = wp_count_posts( $post_type );
	$post_status = array(
		'all' => 0,
		'publish' => 0,
		'draft' => 0,
		'pending' => 0,
		'trash' => 0,
		'private' => 0,
	);
	foreach ( $post_status as $status => $value ) {
		if ( isset( $count->$status ) ) {
			$post_status[ $status ] = $count->$status;
			if ( 'trash' === $status ) {
				continue;
			}
			$post_status['all'] += $count->$status;
		}
	}
	$statuses = array();

	/**
	 * Build statuses array
	 */
	if ( ! empty( $post_status ) ) {
		$url = add_query_arg( 'page', $slug, admin_url( 'admin.php' ) );
		foreach ( $post_status as $status => $count ) {
			$classes = array( $status );
			if ( 'all' === $status ) {
				$statuses[] = array(
					'status' => 'all',
					'label' => __( 'All', 'cp' ),
					'count' => $count,
					'url' => $url,
					'classes' => $classes,
					'current' => 'any' === $current_status,
				);
			} elseif ( $count > 0 ) {
				$url = add_query_arg( 'status', $status, $url );
				$statuses[] = array(
					'status' => $status,
					'label' => coursepress_status_title( $status ),
					'count' => $count,
					'url' => $url,
					'classes' => $classes,
					'current' => $status === $current_status,
				);
			}
		}
	}

	return $statuses;
}

/**
 * Get title by post_status
 *
 * @param string $status
 * @return string
 */
function coursepress_status_title( $status ) {
	$available_statuses = array(
		'draft'   => __( 'Draft', 'cp' ),
		'publish' => __( 'Publish', 'cp' ),
		'pending' => __( 'Pending', 'cp' ),
		'private' => __( 'Private', 'cp' ),
		'trash'   => __( 'Trash', 'cp' ),
	);
	$title = ! empty( $available_statuses[ $status ] ) ? $available_statuses[ $status ] : '';

	return $title;
}

/**
 * Get the course title.
 *
 * @param int $course_id Optional. If null will return course title of the current serve course.
 *
 * @return CoursePress_Course|null
 */
function coursepress_get_course_title( $course_id = 0 ) {
	$course = coursepress_get_course( $course_id );

	if ( ! is_wp_error( $course ) ) {
		return $course->__get( 'post_title' ); }

	return null;
}

/**
 * Get the course summary of the given course ID.
 *
 * @param int $course_id    Optional. If omitted, will return summary of the current serve course.
 * @param int $length       Optional. The character length of the summary to return.
 *
 * @return null|string
 */
function coursepress_get_course_summary( $course_id = 0, $length = 140 ) {
	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return null; }

	return $course->get_summary( $length );
}

/**
 * Helper function to get course description.
 *
 * @param int $course_id
 *
 * @return null|string
 */
function coursepress_get_course_description( $course_id = 0 ) {
	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return null; }

	return $course->get_description();
}

/**
 * Return's course media base on set settings.
 *
 * @param int $course_id
 * @param int $width
 * @param int $height
 *
 * @return null|string
 */
function coursepress_get_course_media( $course_id = 0, $width = 220, $height = 220 ) {
	$course = coursepress_get_course( $course_id );

	if ( ! is_wp_error( $course ) ) {
		return $course->get_media( $width, $height ); }

	return null;
}

/**
 * Returns course start and end date, separated by set separator.
 *
 * @param int $course_id
 * @param string $separator
 *
 * @return string|null
 */
function coursepress_get_course_availability_dates( $course_id = 0, $separator = ' - ' ) {
	$course = coursepress_get_course( $course_id );
	if ( is_wp_error( $course ) ) {
		return null;
	}
	return $course->get_course_dates( $separator );
}

/**
 * Returns course enrollment start and end date, separated by set separator.
 *
 * @param int $course_id
 * @param string $separator
 *
 * @return string|null
 */
function coursepress_get_course_enrollment_dates( $course_id = 0, $separator = ' - ' ) {
	$course = coursepress_get_course( $course_id );
	if ( is_wp_error( $course ) ) {
		return null;
	}
	return $course->get_enrollment_dates( $separator );
}

/**
 * Returns course enrollment start and end date, separated by set separator.
 *
 * @param int $course_id
 * @param string $separator
 *
 * @return string|null
 */
function coursepress_get_course_enrollment_type( $course_id = 0, $separator = '' ) {
	$course = coursepress_get_course( $course_id );
	if ( is_wp_error( $course ) ) {
		return null;
	}
	return $course->get_course_enrollment_type( $separator );
}

function coursepress_get_button_default_attrs() {
	$texts = array(
		'course_id' => coursepress_get_course_id(),
		'access_text' => __( 'Start Learning', 'cp' ),
		'class' => '',
		'continue_learning_text' => __( 'Continue Learning', 'cp' ),
		'course_expired_text' => __( 'Not available', 'cp' ),
		'course_full_text' => __( 'Course Full', 'cp' ),
		'details_text' => __( 'Details', 'cp' ),
		'enrollment_closed_text' => __( 'Enrollments Closed', 'cp' ),
		'enrollment_finished_text' => __( 'Enrollments Finished', 'cp' ),
		'enroll_text' => __( 'Enroll Now!', 'cp' ),
		'instructor_text' => __( 'Access Course', 'cp' ),
		'list_page' => false,
		'not_started_text' => __( 'Not Available', 'cp' ),
		'passcode_text' => __( 'Passcode Required', 'cp' ),
		'prerequisite_text' => __( 'Pre-requisite Required', 'cp' ),
		'signup_text' => __( 'Enroll Now!', 'cp' ),
	);

	return $texts;
}

/**
 * Returns course enrollment button.
 *
 * @deprecated 1.0.0 Use course_join_button shortcode
 * @see CoursePress_Data_Shortcode_CourseTemplate::get_course_join_button()
 * @param int $course_id
 *
 * @return string
 */
function coursepress_get_course_enrollment_button( $course_id = 0, $args = array() ) {
	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return null;
	}

	$defaults = coursepress_get_button_default_attrs();

	$args = wp_parse_args( $args, $defaults );
	$button_option = 'enroll';
	$course_id = $course->__get( 'ID' );
	$user = coursepress_get_user();
	$link = '';
	$link_text = '';

	if ( $user->is_enrolled_at( $course_id ) ) {
		$link = $course->get_units_url();
		$link_text = $args['access_text'];
		$button_option = false;
	} else {
		$user_can_enroll = $course->user_can_enroll();
		if ( $user_can_enroll ) {
			$enrollment_type = $course->__get( 'enrollment_type' );
			$button_option = 'enroll';
			$link_text = $args['enroll_text'];
			$link_args = array(
				'course_id' => $course_id,
				'action'    => 'coursepress_enroll',
				'_wpnonce' => wp_create_nonce( 'coursepress_nonce' ),
			);
			if ( is_user_logged_in() ) {
				$link = add_query_arg( $link_args, admin_url( 'admin-ajax.php' ) );
				if ( 'prerequisite' === $enrollment_type ) {
					$courses = $course->__get( 'enrollment_prerequisite' );
					$messages = array();

					if ( is_array( $courses ) ) {
						foreach ( $courses as $_course_id ) {
							$_course = coursepress_get_course( $_course_id );
							$_course_link = coursepress_create_html(
								'a',
								array(
									'href' => $_course->get_permalink(),
									'target' => '_blank',
									'rel' => 'bookmark',
								),
								$_course->post_title
							);

							if ( ! $user->is_course_completed( $_course_id ) ) {
								$messages[] = coursepress_create_html(
									'p',
									array(),
									sprintf( __( 'You need to complete %s to take this course.', 'cp' ), $_course_link )
								);
							}
						}
					}
				} elseif ( 'passcode' === $enrollment_type ) {
					$link = '';
					$link_text = '';
					$args = array(
						'course_id' => $course_id,
						'label_text' => __( 'Enter passcode', 'cp' ),
						'button_text' => __( 'Enroll', 'cp' ),
						'cookie_name' => 'cp_incorrect_passcode_' . COOKIEHASH,
					);

					coursepress_render( 'views/front/passcode-form', $args );
				}

				if ( ! empty( $messages ) ) {
					echo implode( ' ', $messages );
					$link = '';
					$link_text = '';
				}
			} else {
				$link = add_query_arg( $link_args, $course->get_permalink() );
				$link = coursepress_get_student_login_url( $link );
			}
		}
	}
	$attr = array(
		'class' => 'course-enroll-button'. ( isset( $args['class'] ) ? ' ' . $args['class']:'' ),
	);
	if ( ! empty( $link ) ) {
		$attr['href'] = $link;
		$attr['_wpnonce'] = wp_create_nonce( 'coursepress_nonce' );
		$button = coursepress_create_html( 'a', $attr, $link_text );
	} else {
		$button = coursepress_create_html( 'span', $attr, $link_text );
	}
	$button = apply_filters( 'coursepress_enroll_button', $button, $course_id, $user->ID, $button_option );
	echo $button;
}

/**
 * Returns course instructor links, separated by set separator.
 *
 * @param int $course_id
 * @param string $sepatator
 *
 * @return string|null
 */
function course_get_course_instructor_links( $course_id = 0, $sepatator = ' ' ) {
	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return null; }

	$instructors = $course->get_instructors_link();

	if ( ! empty( $instructors ) ) {
		return implode( $sepatator, $instructors ); }

	return null;
}

/**
 * Returns course structure filter base on current user.
 *
 * @param int $course_id
 * @param bool $show_details
 *
 * @return null|string
 */
function coursepress_get_course_structure( $course_id = 0, $show_details = false ) {
	$course = coursepress_get_course( $course_id );

	if ( ! is_wp_error( $course ) ) {
		return $course->get_course_structure( $show_details );
	}

	return null;
}

/**
 * Returns CoursePress courses url.
 *
 * @return string
 */
function coursepress_get_main_courses_url() {
	$main_slug = coursepress_get_setting( 'slugs/course', 'courses' );

	return home_url( '/' ) . trailingslashit( $main_slug );
}

/**
 * Returns the course's URL structure.
 *
 * @param $course_id
 *
 * @return string
 */
function coursepress_get_course_permalink( $course_id = 0 ) {
	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return null; }

	return $course->get_permalink();
}

/**
 * Get the course's submenu links.
 *
 * @return array of submenu items.
 */
function coursepress_get_course_submenu() {
	$course = coursepress_get_course(); // Submenu only works on CoursePress pages
	if ( is_wp_error( $course ) || ! is_user_logged_in() ) {
		return null;
	}
	/**
	 * set current-menu-item
	 */
	$current = get_query_var( 'coursepress' );
	/**
	 * course ID
	 */
	$course_id = $course->__get( 'ID' );
	$menus = array();
	$user = coursepress_get_user();
	$is_enrolled = $user->is_enrolled_at( $course_id );
	if ( ! $is_enrolled ) {
		$is_super = $user->is_super_admin();
		if ( ! $is_super ) {
			return $menus;
		}
	}
	/**
	 * Units
	 */
	$menus = array(
		'units' => array(
			'label' => __( 'Units', 'cp' ),
			'url' => $course->get_units_url(),
			'classes' => array( 'submenu-units' ),
		),
	);
	if ( 'unit-archive' === $current || 'step' === $current ) {
		$menus['units']['classes'][] = 'current-menu-item';
	}

	// Course Notifications.
	$menus['notifications'] = array(
		'label' => __( 'Notifications', 'cp' ),
		'url' => esc_url( $course->get_notifications_url() ),
		'classes' => array( 'submenu-notifications' ),
	);
	if ( 'notifications' === $current ) {
		$menus['notifications']['classes'][] = 'current-menu-item';
	}

	/**
	 * forum
	 */
	if ( $course->__get( 'allow_discussion' ) ) {
		$menus['discussions'] = array(
			'label' => __( 'Forum', 'cp' ),
			'url' => esc_url( $course->get_discussion_url() ),
			'classes' => array( 'submenu-discussions' ),
		);
		if ( 'forum' === $current ) {
			$menus['discussions']['classes'][] = 'current-menu-item';
		}
	}
	/**
	 * workbook
	 */
	if ( $course->__get( 'allow_workbook' ) ) {
		$menus['workbook'] = array(
			'label' => __( 'Workbook', 'cp' ),
			'url' => esc_url( $course->get_workbook_url() ),
			'classes' => array( 'submenu-workbook' ),
		);
		if ( 'workbook' === $current ) {
			$menus['workbook']['classes'][] = 'current-menu-item';
		}
	}
	/**
	 * grades
	 */
	if ( $course->__get( 'allow_grades' ) ) {
		$menus['grades'] = array(
			'label' => __( 'Grades', 'cp' ),
			'url' => esc_url( $course->get_grades_url() ),
			'classes' => array( 'submenu-grades' ),
		);
		if ( 'grades' === $current ) {
			$menus['grades']['classes'][] = 'current-menu-item';
		}
	}
	// Add course details link at the last
	$menus['course-details'] = array(
		'label' => __( 'Course Details', 'cp' ),
		'url' => esc_url( $course->get_permalink() ),
		'classes' => array( 'submenu-info' ),
	);
	if ( empty( $current ) ) {
		$menus['course-details']['classes'][] = 'current-menu-item';
	}
	/**
	 * fill class if empty
	 */
	foreach ( $menus as $menu_id => $menu ) {
		if ( ! isset( $menu['classes'] ) ) {
			$menus[ $menu_id ]['classes'] = array();
		}
		$menus[ $menu_id ]['classes'][] = 'menu-item';
		$menus[ $menu_id ]['classes'][] = sprintf( 'menu-item-%s', esc_attr( $menu_id ) );
	}
	/**
	 * Fired to allow adding course menu.
	 *
	 * @since 3.0
	 */
	$menus = apply_filters( 'coursepress_course_submenu', $menus, $course );
	return $menus;
}

/**
 * Gets the current serve course cycle.
 *
 * @since 3.0
 */
function coursepress_get_current_course_cycle( $args = array() ) {
	/**
	 * @var array $_course_module An array of current module data.
	 * @var object $_course_step
	 */
	global $coursepress_virtualpage, $_course_module, $_course_step, $_coursepress_previous;
	$args = wp_parse_args(
		$args,
		array(
			'container_next' => 'div',
			'container_previous' => 'div',
			'navigation_tag' => 'div',
			'navigation' => 'bottom',
			'navigation_separator' => '',
			'next' => __( 'Next', 'cp' ),
			'previous' => __( 'Previous', 'cp' ),
		)
	);
	$course = coursepress_get_course();
	if ( is_wp_error( $course ) ) {
		return null;
	}
	$is_course_started = $course->is_course_started();
	if ( ! $is_course_started ) {
		return __( 'Course is not available yet', 'cp' );
	}
	$unit = coursepress_get_unit();
	if ( is_wp_error( $unit ) ) {
		return null;
	}
	if ( ! $coursepress_virtualpage instanceof CoursePress_VirtualPage ) {
		return null;
	}
	$vp = $coursepress_virtualpage;
	$vp_type = $vp->__get( 'type' );
	$_coursepress_previous = $course->get_units_url();
	$course_id = $course->__get( 'ID' );
	$unit_id = $unit->__get( 'ID' );
	$user = coursepress_get_user();
	if ( ! in_array( $vp_type, array( 'unit', 'module', 'step' ), true ) ) {
		return null;
	}
	$view_mode = $course->get_view_mode();
	$form_attr = array(
		'method' => 'post',
		'action' => admin_url( 'admin-ajax.php?action=coursepress_submit' ),
		'class' => 'coursepress-course coursepress-course-form',
	);
	$template = coursepress_create_html(
		'input',
		array(
			'type' => 'hidden',
			'name' => 'course_id',
			'value' => $course_id,
		)
	);
	$template .= coursepress_create_html(
		'input',
		array(
			'type' => 'hidden',
			'name' => 'unit_id',
			'value' => $unit->__get( 'ID' ),
		)
	);
	$template .= coursepress_create_html(
		'input',
		array(
			'type' => 'hidden',
			'name' => 'type',
			'value' => $vp_type,
		)
	);
	$template .= coursepress_create_html(
		'input',
		array(
			'type' => 'hidden',
			'name' => 'module_id',
			'value' => $_course_module ? $_course_module['id'] : 0,
		)
	);
	$template .= coursepress_create_html(
		'input',
		array(
			'type' => 'hidden',
			'name' => 'step_id',
			'value' => $_course_step ? $_course_step->__get( 'ID' ) : 0,
		)
	);
	$referer_url = '';
	$has_access = coursepress_has_access( $course_id, $unit_id );

	if ( $has_access['access'] ) {

		if ( 'focus' === $view_mode ) {
			if ( 'unit' === $vp_type ) {
				$template    .= $vp->create_html(
					'div',
					array( 'class' => 'unit-description' ),
					apply_filters( 'the_content', $unit->get_description() )
				);
				$referer_url = $unit->get_permalink();

			} elseif ( 'module' === $vp_type && $_course_module ) {
				$has_access = coursepress_has_access( $course_id, $unit_id, $_course_module['id'] );

				if ( $has_access['access'] ) {
					$template .= $vp->create_html(
						'h3',
						array(),
						$_course_module['title']
					);

					$description = htmlspecialchars_decode( $_course_module['description'] );
					$template    .= $vp->create_html(
						'div',
						array( 'class' => 'module-description' ),
						apply_filters( 'the_content', $description )
					);
					$referer_url = $_course_module['url'];

					// Record module visit
					$user->add_visited_module( $course_id, $unit_id, $_course_module['id'] );
				}
			} elseif ( 'step' === $vp_type && $_course_step ) {
				$has_access = coursepress_has_access( $course_id, $unit_id, $_course_module['id'], $_course_step->__get( 'ID' ) );

				if ( $has_access['access'] ) {
					$template    .= $_course_step->template();
					$referer_url = $_course_step->get_permalink();
					$user->add_visited_step( $course_id, $unit_id, $_course_step->__get( 'ID' ) );

					if ( 'input-upload' === $_course_step->__get( 'module_type' ) ) {
						$form_attr['enctype'] = 'multipart/form-data';
					}
				}
			}
		}
	}

	$previous = coursepress_create_html(
		$args['container_previous'],
		array( 'class' => 'course-previous-item' ),
		coursepress_get_previous_course_cycle_link( $args['previous'] )
	);

	$template .= coursepress_create_html(
		'input',
		array(
			'type' => 'hidden',
			'name' => 'redirect_url',
			'value' => coursepress_get_link_cycle( 'next' ),
		)
	);

	$template .= coursepress_create_html(
		'input',
		array(
			'type' => 'hidden',
			'name' => 'referer_url',
			'value' => $referer_url,
		)
	);

	$submit = '';

	if ( $has_access['access'] ) {
		$next = coursepress_create_html(
			$args['container_next'],
			array( 'class' => 'course-next-item' ),
			coursepress_create_html(
				'button',
				array(
					'type'  => 'submit',
					'class' => 'button button-next coursepress-next-cycle',
					'name'  => 'submit_module',
					'value' => 1,
				),
				$args['next']
			)
		);
		if ( $_course_step ) {
			$is_answerable = $_course_step->is_answerable();
			if ( $is_answerable ) {
				$submit = coursepress_create_html(
					'button',
					array(
						'type'  => 'submit',
						'class' => 'button button-next coursepress-next-cycle',
						'name'  => 'submit_module',
						'value' => 1,
					),
					__( 'Submit', 'cp' )
				);
			}
		}
	} else {
		$next = '';
		$template .= coursepress_create_html(
			'p',
			array(
				'class' => 'description no-access-description',
			),
			$has_access['message']
		);
	}
	$navigation = coursepress_create_html(
		$args['navigation_tag'],
		array( 'class' => 'course-step-nav' ),
		$previous . $args['navigation_separator'] . $next
	);
	/**
	 * Navigation position
	 */
	if ( 'top' === $args['navigation'] ) {
		$template = $navigation.$template;
	} else {
		$template .= $navigation;
	}
	$template = coursepress_create_html(
		'form',
		$form_attr,
		$template.$submit
	);
	return $template;
}

function coursepress_has_access( $course_id, $unit_id = 0, $module_id = 0, $step_id = 0 ) {
	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return null;
	}

	$unit = coursepress_get_unit( $unit_id );

	if ( is_wp_error( $unit ) ) {
		return null;
	}

	$user = coursepress_get_user();
	$force_current_unit_completion = $course->__get( 'force_current_unit_completion' );
	$force_current_unit_successful_completion = $course->__get( 'force_current_unit_successful_completion' );
	$with_modules = $course->is_with_modules();
	$has_access = true;
	$prev_unit = $unit->get_previous_unit();
	$message = '';
	$user_id = $user->ID;

	if ( $prev_unit ) {
		$has_access = $user->is_unit_completed( $course_id, $prev_unit->ID );
		if ( ! $has_access ) {
			$message = __( 'You need to complete the previous unit before this unit!', 'cp' );
		}
	}

	if ( $has_access ) {
		if ( $with_modules ) {
			if ( (int) $module_id > 0 ) {
				$modules = $unit->get_modules_with_steps();

				if ( $modules ) {
					foreach ( $modules as $id => $module ) {
						$module_completed = $user->is_module_completed( $course_id, $unit_id, $id );
						if ( $id !== $module_id && ! $module_completed ) {
							$has_access = true;
							$steps = $module['steps'];
							if ( $steps ) {
								foreach ( $steps as $step ) {
									if ( empty( $step->mandatory ) && empty( $step->assessable ) ) {
										continue;
									}
									$has_access = false;
								}
							}
							if ( ! $has_access ) {
								$message = __( 'You need to complete the previous module(s) before this module!', 'cp' );
							}
						}

						if ( $step_id > 0 && $module['steps'] ) {
							$steps = $module['steps'];

							if ( $steps ) {
								foreach ( $steps as $step ) {
									$_step_id = $step->__get( 'ID' );
									$is_step_completed = $user->is_step_completed( $course_id, $unit_id, $_step_id );

									if ( ! $is_step_completed && $step_id !== $_step_id ) {
										$has_access = false;
										$message = __( 'You need to complete all previous steps before this step!', 'cp' );
										break;
									}

									if ( $step_id === $_step_id ) {
										break;
									}
								}
							}
						}

						if ( $id === $module_id ) {
							break;
						}
					}
				}
			}
		} else {
			$steps = $unit->get_steps();

			if ( $steps && (int) $step_id > 0 ) {
				foreach ( $steps as $step ) {
					$_step_id = $step->__get( 'ID' );

					$is_step_completed = $user->is_step_completed( $course_id, $unit_id, $_step_id );

					if ( ! $is_step_completed && $step_id !== $_step_id ) {
						$has_access = false;
						$message = __( 'You need to complete all previous steps before this step!', 'cp' );
						break;
					}

					if ( $step_id === $_step_id ) {
						break;
					}
				}
			}
		}
	}

	return array(
		'access' => $has_access,
		'message' => $message,
		);
}

function coursepress_get_link_cycle( $type = 'next' ) {
	global $coursepress_virtualpage, $_course_module, $_course_step;

	$course = coursepress_get_course();
	$unit = coursepress_get_unit();

	if ( is_wp_error( $course ) || is_wp_error( $unit ) ) {
		return false;
	}

	$user = coursepress_get_user();

	$vp = $coursepress_virtualpage;
	$vp_type = $vp->__get( 'type' );
	$with_modules = $course->is_with_modules();
	$has_access = $user->has_access_at( $course->__get( 'ID' ) );
	$module_id = ! empty( $_course_module['id'] ) ? $_course_module['id'] : false;
	$module_url = ! empty( $_course_module['url'] ) ? $_course_module['url'] : false;
	$steps = $unit->get_steps( ! $has_access, $with_modules, $module_id );
	$link = '';

	if ( 'previous' === $type ) {
		$previous_unit = $unit->get_previous_unit();
		$prev_module = $unit->get_previous_module( $module_id );
		$prev_module_id = ! empty( $prev_module['id'] ) ? $prev_module['id'] : false;
		$prev_step = $_course_step ? $_course_step->get_previous_step() : false;
		if ( $prev_step ) {
			$link = $prev_step->get_permalink();
		} elseif ( $module_url && 'module' !== $vp_type && 'unit' !== $vp_type ) {
			$link = $module_url;
		} elseif ( $prev_module ) {
			$link = $prev_module['url'];
			$prev_steps = $unit->get_steps( ! $has_access, $with_modules, $prev_module_id );
			if ( $prev_steps ) {
				$last_steps = array_pop( $prev_steps );
				$link = $last_steps->get_permalink();
			}
		} elseif ( 'unit' !== $vp_type ) {
			$link = $unit->get_permalink();
		} elseif ( $previous_unit ) {
			$link = $previous_unit->get_unit_url();
			$prev_modules = $previous_unit->get_modules_with_steps();
			$prev_steps = $previous_unit->get_steps();
			if ( $prev_modules ) {
				$last_module = array_pop( $prev_modules );
				$link = $last_module['url'];
				if ( ! empty( $last_module['steps'] ) ) {
					$last_step = array_pop( $last_module['steps'] );
					$link = $last_step->get_permalink();
				}
			} elseif ( $prev_steps ) {
				$prev_step = array_pop( $prev_steps );
				$link = $prev_step->get_permalink();
			}
		} else {
			$link = $course->get_units_url();
		}
	} else {
		// for Next link
		$next_unit = $unit->get_next_unit();
		$next_module = $unit->get_next_module( $module_id );
		$next_step = $_course_step ? $_course_step->get_next_step() : false;
		if ( $next_step ) {
			$link = $next_step->get_permalink();
		} elseif ( $module_url && 'unit' === $vp_type ) {
			$link = $module_url;
		} elseif ( $steps && 'step' !== $vp_type ) {
			$first_step = array_shift( $steps );
			$link = $first_step->get_permalink();
		} elseif ( $next_module ) {
			$link = $next_module['url'];
		} elseif ( $next_unit ) {
			$link = $next_unit->get_unit_url();
		} else {
			$link = $course->get_permalink() . trailingslashit( 'completion/validate' );
		}
	}

	return $link;
}

/**
 * Returns the course previous cycle.
 *
 * @param string $label
 *
 * @return null|string
 */
function coursepress_get_previous_course_cycle_link( $label = '' ) {
	global $coursepress_virtualpage;

	if ( ! $coursepress_virtualpage instanceof CoursePress_VirtualPage ) {
		return null;
	}

	$course = coursepress_get_course();

	if ( is_wp_error( $course ) ) {
		return null;
	}
	if ( empty( $label ) ) {
		$label = __( 'Previous', 'cp' );
	}

	$link = coursepress_get_link_cycle( 'previous' );

	return coursepress_create_html(
		'a',
		array(
			'href' => esc_url( $link ),
			'class' => 'button previous-button coursepress-previous-cycle',
		),
		$label
	);
}

/**
 * Returns the course next cycle.
 *
 * @param string $label
 *
 * @return null|string
 */
function coursepress_get_next_course_cycle_link( $label = '' ) {
	global $coursepress_virtualpage;

	if ( ! $coursepress_virtualpage instanceof CoursePress_VirtualPage ) {
		return null;
	}

	$course = coursepress_get_course();

	if ( is_wp_error( $course ) ) {
		return null;
	}

	if ( empty( $label ) ) {
		$label = __( 'Next', 'cp' );
	}

	$link = coursepress_get_link_cycle( 'next' );

	return coursepress_create_html(
		'a',
		array(
			'href' => esc_url( $link ),
			'class' => 'button next-button coursepress-next-cycle',
		),
		$label
	);
}

function coursepress_course_update_setting( $course_id, $settings = array() ) {
	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) || empty( $settings ) ) {
		return false;
	}

	$settings = wp_parse_args( $settings, $course->get_settings() );

	update_post_meta( $course_id, 'course_settings', $settings );

	return true;
}

/**
 * Get course setting value.
 *
 * @param int $course_id Course ID.
 * @param string $key Setting key.
 * @param bool $default Default value.
 *
 * @return bool
 */
function coursepress_course_get_setting( $course_id, $key, $default = false ) {

	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) || empty( $key ) ) {
		return $default;
	}

	$settings = $course->get_settings();

	if ( isset( $settings[ $key ] ) ) {
		return $settings[ $key ];
	}

	return $default;
}

/**
 * Create new course category from text.
 *
 * @param string $name New category name.
 *
 * @return bool|WP_Term
 */
function coursepress_create_course_category( $name = '' ) {

	if ( empty( $name ) ) {
		return false;
	}

	// @todo: Implement capability check.

	$result = wp_insert_term( $name, 'course_category' );

	// If term was created, return the term.
	if ( ! is_wp_error( $result ) && ! empty( $result['term_id'] ) ) {
		return get_term( $result['term_id'], 'course_category' );
	}

	return false;
}

/**
 * Get instructors list of the course.
 *
 * @param int $course_id Course ID.
 *
 * @return array
 */
function coursepress_get_course_instructors( $course_id ) {

	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return array();
	}

	$instructors = $course->get_instructors();

	if ( ! empty( $instructors ) ) {
		return $instructors;
	}

	return array();
}

/**
 * Get facilitators list of the course.
 *
 * @param int $course_id Course ID.
 * @param array $args
 *
 * @return array
 */
function coursepress_get_course_facilitators( $course_id ) {

	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return array();
	}

	$facilitators = $course->get_facilitators();

	if ( ! empty( $facilitators ) ) {
		return $facilitators;
	}

	return array();
}

function coursepress_delete_course( $course_id ) {

	// Continue only capable.
	if ( ! CoursePress_Data_Capabilities::can_delete_course( $course_id ) ) {
		return false;
	}

	$course = coursepress_get_course( $course_id );
	if ( is_wp_error( $course ) ) {
		return $course;
	}
	/**
	 * delete forums
	 */
	$forums = new CoursePress_Admin_Forums();
	$forums_ids = $forums->get_by_course_id( $course_id );
	if ( is_array( $forums_ids ) ) {
		foreach ( $forums_ids as $forum_id ) {
			wp_delete_post( $forum_id, true );
		}
	}
	/**
	 * Delete units
	 */
	$units = $course->get_units( false );
	if ( $units ) {
		foreach ( $units as $unit ) {
			$unit_id = $unit->__get( 'ID' );
			// Delete all steps
			$steps = $unit->get_steps( false );
			foreach ( $steps as $step ) {
				$step_id = $step->__get( 'ID' );
				if ( $step_id > 0 ) {
					wp_delete_post( $step_id, true );
				}
			}
			wp_delete_post( $unit_id );
		}
	}
	/**
	 * Remove students of this course
	 */
	$students = $course->get_students();
	if ( $students ) {
		foreach ( $students as $student ) {
			// Remove user from deleted course
			$student->remove_course_student( $course_id );
		}
	}
	/**
	 * Delete course instructors
	 */
	$instructors = $course->get_instructors();
	if ( $instructors ) {
		foreach ( $instructors as $instructor ) {
			coursepress_delete_course_instructor( $instructor->ID, $course_id );
		}
	}
	/**
	 * Update course numbers
	 */
	$course->save_course_number( $course_id, $course->post_title, array( $course_id ) );
	// Now delete the course
	wp_delete_post( $course_id );
	/**
	 * Fired whenever a course is deleted.
	 */
	do_action( 'coursepress_course_deleted', $course_id, $course );
}

/**
 * Get units of a course.
 *
 * @param int $course_id Course ID.
 *
 * @return array
 */
function coursepress_get_course_units( $course_id ) {

	$course = coursepress_get_course( $course_id );

	if ( is_wp_error( $course ) ) {
		return array();
	}

	$units = $course->get_units();

	if ( ! empty( $units ) ) {
		return $units;
	}

	return array();
}

/**
 * Get line with statuses
 *
 * @param array $statuses
 */
function cp_subsubsub( $statuses ) {
	$count = count( $statuses );
	if ( $count ) {
		echo '<ul class="subsubsub">';
		foreach ( $statuses as $status ) {
			printf( '<li class="%s">', esc_attr( implode( $status['classes'], ' ' ) ) );
			printf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_attr( $status['url'] ),
				$status['current']? ' class="current"':'',
				esc_html( $status['label'] ),
				esc_html( $status['count'] )
			);
			if ( $count-- > 1 ) {
				echo ' |';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}

/**
 * Get cp post type title
 *
 * @return string
 */
function get_cp_type_title( $post_type ) {
	$titles = array(
		'notification' => __( 'Notification', 'cp' ),
		'discussions' => __( 'Discussion', 'cp' ),
	);
	$title = ! empty( $titles[ $post_type ] ) ? $titles[ $post_type ] : '';

	return $title;
}

/**
 * Get all cp post types
 *
 * @global object $coursepress_core
 * @return array
 */
function get_cp_types() {
	global $coursepress_core;
	$post_types = array(
		'course' => $coursepress_core->course_post_type,
		'notification' => $coursepress_core->notification_post_type,
		'discussion' => $coursepress_core->discussions_post_type,
	);

	return $post_types;
}

/**
 * Get sp post type by name
 *
 * @param string $name
 * @return string|bool
 */
function get_cp_type( $name ) {
	$post_types = get_cp_types();
	$post_type = $name && ! empty( $post_types[ $name ] ) ? $post_types[ $name ] : false;

	return $post_type;
}

/**
 * Check post type
 *
 * @param int $post_id
 * @param string $type Post type or cp_type
 * @return bool
 */
function coursepress_is_type( $post_id, $type ) {
	$post_type = get_cp_type( $type );
	if ( $post_type ) {
		$type = $post_type;
	}
	$current_post_type = get_post_field( 'post_type', $post_id );
	return $type === $current_post_type;
}

/**
 * Change cp post.
 *
 * @param int $post_id Post ID.
 * @param string $status New status (publish/draft/pending/trash/restore/delete).
 * @param string $type post type (course/unit/alert/discussion).
 * @param bool $caps_check Should check capability?
 *
 * @return bool
 */
function coursepress_change_post( $post_id, $status, $type, $caps_check = true ) {
	/**
	 * sanitize post id
	 */
	$post_id = absint( $post_id );
	// Allowed statuses to change.
	$allowed_statuses = array( 'publish', 'draft', 'pending', 'trash', 'restore', 'delete' );

	if ( empty( $post_id ) || ! in_array( $status, $allowed_statuses, true ) || ! coursepress_is_type( $post_id, $type ) ) {

		/**
		 * Perform actions when post status not changed.
		 *
		 * @param int $post_id Post ID.
		 * @param int $status Status.
		 *
		 * @since 3.0.0
		 */
		do_action( 'coursepress_' . $type . '_status_change_fail', $post_id, $status );
		return false;
	}

	// If capability needs to be checked.
	if ( $caps_check ) {
		/**
		 * Check capability for the post.
		 * Remember you capability checking method name should match
		 * following format.
		 * For delete, trash, and restore: can_delete_POSTTYPE
		 * For other status changes: can_change_POSTTYPE_status
		 */
		$cap_method = in_array( $status, array(
			'trash',
			'delete',
			'restore',
		), true ) ? 'can_delete_' . $type : 'can_change_' . $type . '_status';
		if ( method_exists( 'CoursePress_Data_Capabilities', $cap_method ) && ! CoursePress_Data_Capabilities::$cap_method( $post_id ) ) {
			// This action hook is documented above.
			do_action( 'coursepress_' . $type . '_status_change_fail', $post_id, $status );

			return false;
		}
	}

	switch ( $status ) {
		case 'trash':
			wp_trash_post( $post_id );
			break;

		case 'restore':
			wp_untrash_post( $post_id );
			break;

		case 'delete':
			$delete_function = "coursepress_delete_$type";
			if ( function_exists( $delete_function ) ) {
				call_user_func( $delete_function, $post_id );
			} else {
				wp_delete_post( $post_id );
			}
			break;

		default:
			$post = array(
				'ID' => $post_id,
				'post_status' => $status,
			);
			// Update the post status.
			if ( is_wp_error( wp_update_post( $post ) ) ) {

				// This action hook is documented above.
				do_action( 'coursepress_' . $type . '_status_change_fail', $post_id, $status );
				return false;
			}
			break;
	}

	/**
	 * Perform actions when post status is changed.
	 *
	 * var $post_id The post id.
	 * var $status The new status.
	 *
	 * @since 3.0.0
	 */
	do_action( 'coursepress_' . $type . '_status_changed', $post_id, $status );
	return true;
}

function coursepress_get_course_step( $step_id = 0 ) {
	$step_type = get_post_meta( (int) $step_id, 'module_type', true );

	$class = array(
		'text' => 'CoursePress_Step_Text',
		'text_module' => 'CoursePress_Step_Text', // Legacy type
		'image' => 'CoursePress_Step_Image',
		'video' => 'CoursePress_Step_Video',
		'audio' => 'CoursePress_Step_Audio',
		'discussion' => 'CoursePress_Step_Discussion',
		'download' => 'CoursePress_Step_FileDownload',
		'zipped' => 'CoursePress_Step_Zip',
		'input-upload' => 'CoursePress_Step_FileUpload',
		'input-quiz' => 'CoursePress_Step_Quiz',
		'input-checkbox' => 'CoursePress_Step_Checkbox', // Legacy class
		'input-radio' => 'CoursePress_Step_Radio', // Legacy class
		'radio_input_module' => 'CoursePress_Step_Radio', // Legacy type
		'input-select' => 'CoursePress_Step_Select', // Legacy class
		'input-textarea' => 'CoursePress_Step_InputText',
		'input-text' => 'CoursePress_Step_InputText',
		'text_input_module' => 'CoursePress_Step_Written', // Legacy type
		'input-written' => 'CoursePress_Step_Written',
		'input-form' => 'CoursePress_Step_Form', // Legacy class
	);

	if ( isset( $class[ $step_type ] ) ) {
		$step_class = $class[ $step_type ];
		$step_class = new $step_class( $step_id );

		return $step_class;
	}

	return false;
}

function coursepress_get_post_object( $post_id ) {
	$post_type = get_post_type( $post_id );

	if ( 'module' === $post_type ) {
		return coursepress_get_course_step( $post_id );
	} elseif ( 'unit' === $post_type ) {
		return coursepress_get_unit( $post_id );
	} elseif ( 'course' === $post_type ) {
		return coursepress_get_course( $post_id );
	}

	return false;
}


function coursepress_get_course_object( $post_id ) {
	$post_type = get_post_type( $post_id );

	if ( 'module' === $post_type ) {
		$step = coursepress_get_course_step( $post_id );
		return $step->get_course();

	} elseif ( 'unit' === $post_type ) {
		$unit = coursepress_get_unit( $post_id );
		return $unit->get_course();

	} elseif ( 'course' === $post_type ) {
		return coursepress_get_course( $post_id );
	}

	return false;
}

/**
 * Create or Update course alert.
 *
 * @param int $course_id Course ID.
 * @param string $title Alert title.
 * @param string $content Alert content.
 * @param string $receivers Receivers.
 * @param string $alert_id Alert ID.
 *
 * @return int alert ID.
 */
function coursepress_update_course_alert( $course_id, $title, $content, $receivers, $alert_id ) {
	$action = ! empty( $alert_id ) ? 'update' : 'create' ;

	// Get the course.
	$course = 'all' === $course_id ? $course_id : coursepress_get_course( $course_id );
	// If current user is capable of updating any notification statuses.
	if ( 'all' !== $course && is_wp_error( $course ) ) {
		/**
		 * Perform actions when alert could not be created/updated.
		 *
		 * @param int $course_id Course ID.
		 * @param string $title Alert title.
		 * @param string $content Alert content.
		 *
		 * @since 3.0.0
		 */
		do_action( 'coursepress_alert_' . $action .  '_fail', $course_id, $title, $content, $alert_id );

		return false;
	}

	$post = array(
		'post_title' => sanitize_text_field( $title ),
		'post_content' => $content,
		'post_status' => 'publish',
		'post_type' => 'cp_notification',
	);
	if ( ! empty( $alert_id ) ) {
		$post['ID'] = $alert_id;
	}

	$alert_id = wp_insert_post( $post );
	// If post insertion is failed.
	if ( is_wp_error( $alert_id ) ) {

		// This action hook is documented above.
		do_action( 'coursepress_alert_' . $action .  '_fail', $course_id, $title, $content, $alert_id );

		return false;
	}

	// Set alert course id.
	update_post_meta( $alert_id, 'alert_course', $course_id );
	// Set alert receivers.
	if ( ! empty( $receivers ) ) {
		update_post_meta( $alert_id, 'receivers', $receivers );
	}

	/**
	 * Perform actions when alert insertion was successful.
	 *
	 * @param int $course_id Course ID.
	 * @param string $title Alert title.
	 * @param string $content Alert content.
	 * @param int $alert_id Alert ID.
	 *
	 * @since 3.0.0
	 */
	do_action( 'coursepress_alert_' . $action . '_success', $course_id, $title, $content, $alert_id );

	return $alert_id;
}

function coursepress_get_notification_alert( $alert_id ) {
	$data = array();
	$post = get_post( $alert_id );
	if ( $post && 'cp_notification' === $post->post_type ) {
		$data['id'] = $post->ID;
		$data['title'] = $post->post_title;
		$data['content'] = $post->post_content;
		$data['course_id'] = get_post_meta( $post->ID, 'alert_course', true );
	}
	return $data;
}

/**
 * Get modules by type
 *
 * @since 2.0.0
 *
 * @param string $type module type
 * @param integer $course_id course ID.
 * @return array Array of modules ids.
 */
function coursepress_get_all_modules_ids_by_type( $type, $course_id = null ) {
	global $coursepress_core;
	$args = array(
		'post_type' => $coursepress_core->__get( 'step_post_type' ),
		'fields' => 'ids',
		'suppress_filters' => true,
		'nopaging' => true,
		'meta_key' => 'module_type',
		'meta_value' => $type,
	);
	if ( ! empty( $course_id ) ) {
		$units = coursepress_get_units( $course_id );
		if ( empty( $units ) ) {
			return array();
		}
		$args['post_parent__in'] = $units;
	}
	$items = new WP_Query( $args );
	return $items->posts;
}

/**
 * Get units from course
 */
function coursepress_get_units( $course_id ) {
	global $coursepress_core;
	$args = array(
		'post_type' => $coursepress_core->__get( 'unit_post_type' ),
		'post_parent' => $course_id,
		'nopaging' => true,
		'fields' => 'ids',
		'order' => 'ASC',
	);
	$items = new WP_Query( $args );
	return $items->posts;
}

/**
 * Check is course post type.
 *
 * @param int|WP_Post Post object or post ID.
 * @return bool
 */
function coursepress_is_course( $course ) {
	global $coursepress_core;
	$post_type = get_post_type( $course );
	return $coursepress_core->course_post_type == $post_type;
}

function coursepress_discussion_link( $location, $comment ) {
	global $coursepress_core;
	/**
	 * Check WP_Comment class
	 */
	if ( ! is_a( $comment, 'WP_Comment' ) ) {
		return $location;
	}
	/**
	 * Check post type
	 */
	$unit_post_type = $coursepress_core->discussions_post_type;
	$post_type = get_post_type( $comment->comment_post_ID );
	if ( $unit_post_type !== $post_type ) {
		return $location;
	}
	$course_id = get_post_field( 'post_parent', $comment->comment_post_ID );
	$course = coursepress_get_course( $course_id );
	$discussion_url = $course->get_discussion_url();
	$location = esc_url_raw( $discussion_url . get_post_field( 'post_name', $comment->comment_post_ID ) );

	return $location;
}

/**
 * Invite student to a course.
 *
 * @param int $course_id Course ID.
 * @param object $student_data Student Data(first_name, last_name, email).
 * @return object All invited students
 */
function coursepress_invite_student( $course_id, $student_data ) {
	global $cp_coursepress;
	$course = coursepress_get_course( $course_id );
	if ( is_wp_error( $course ) ) {
		return new WP_Error( 'error', __( 'Selected course does not exits.', 'cp' ) );
	}
	/**
	 * Check course passcode
	 */
	$email_type = 'course_invitation';
	if ( 'passcode' === $course->__get( 'enrollment_type' ) ) {
		$email_type = 'course_invitation_password';
	}
	$email_class = $cp_coursepress->get_class( 'CoursePress_Email' );
	$email_data = $email_class->get_email_data( $email_type );
	if ( empty( $email_data['enabled'] ) ) {
		return new WP_Error( 'error', __( 'Currently we can not send mails.', 'cp' ) );
	}
	/**
	 * sanitize email
	 */
	$email = sanitize_email( $student_data['email'] );
	/**
	 * check user can be add
	 */
	$can_invite_email = $course->can_invite_email( $email );
	if ( is_wp_error( $can_invite_email ) ) {
		return $can_invite_email;
	}
	$tokens = array(
		'COURSE_NAME' => $course->__get( 'post_title' ),
		'COURSE_EXCERPT' => $course->__get( 'post_excerpt' ),
		'COURSE_ADDRESS' => $course->get_permalink(),
		'WEBSITE_ADDRESS' => site_url( '/' ),
		'PASSCODE' => $course->__get( 'enrollment_passcode' ),
		'STUDENT_FIRST_NAME' => $student_data['first_name'],
		'STUDENT_LAST_NAME' => $student_data['last_name'],
	);
	$message = $course->replace_vars( $email_data['content'], $tokens );
	$args = array(
		'message' => $message,
		'to' => $email,
	);
	$email_data = wp_parse_args( $email_data, $args );
	$email_class->sendEmail( $email_type, $email_data );
	$student_data['date'] = $course->date( current_time( 'mysql' ) );
	$student_data['timestamp'] = time();
	$invited_students = $course->__get( 'invited_students' );
	if ( ! $invited_students ) {
		$invited_students = new stdClass();
	}
	/**
	 * Convert student data to object
	 */
	$object = new stdClass();
	foreach ( $student_data as $key => $value ) {
		$object->$key = $value;
	}
	$invited_students->{$email} = $object;
	$course->update_setting( 'invited_students', $invited_students );
	return $student_data;
}

/**
 * Remove invited student from a course.
 *
 * @param int $course_id Course ID.
 * @param string $email Enail address.
 *
 * @return bool
 */
function coursepress_remove_student_invite( $course_id, $email ) {

	// Get the course object.
	$course = coursepress_get_course( $course_id );
	if ( is_wp_error( $course ) ) {
		return false;
	}

	// Get list of invited students.
	$invited_students = $course->__get( 'invited_students' );
	// Make sure given email is there in invited list.
	if ( ! empty( $invited_students ) && isset( $invited_students->{$email} ) ) {
		// Remove given student from list.
		unset( $invited_students->{$email} );
		// Update course data.
		$course->update_setting( 'invited_students', $invited_students );

		return array( 'email' => $email );
	}

	return false;
}

function coursepress_search_students( $args = array() ) {

	$is_search = ! empty( $args['search'] );
	$course_id = ! empty( $args['course_id'] ) ? (int) $args['course_id'] : 0;
	$found = array();

	if ( ! empty( $args['paged'] ) ) {
		$pagenum = (int) $args['paged'];
	}

	if ( $is_search ) {
		$search = $args['search'];
		$search_query = array(
			'search' => $search,
			'search_columns' => array(
				'ID',
				'user_login',
				'user_nicename',
				'user_email',
			),
		);
		$user_query = new WP_User_Query( $search_query );

		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				if ( in_array( 'coursepress_student', $user->roles, true ) ) {
					$cp_user = coursepress_get_user( $user->ID );

					if ( $course_id > 0 ) {
						if ( $cp_user->is_enrolled_at( $course_id ) ) {
							$found[] = $cp_user;
						}
					} else {
						$found[] = $cp_user;
					}
				}
			}
		}
	}

	return $found;
}
/**
 * Get discussions.
 */
function coursepress_get_disscusions( $course ) {
	$args = array(
		'post_type' => 'discussions',
		'post_parent' => $course->ID,
		'post_per_page' => 20,
	);
	$url = $course->get_discussion_url();
	$data = array();
	$posts = get_posts( $args );
	foreach ( $posts as $post ) {
		$post->course_id = $post->post_parent;
		$post->course_title = ! empty( $course->ID ) ? get_the_title( $course->ID ) : __( 'All courses', 'cp' );
		$post->course_id = ! empty( $course->ID ) ? $course->ID : 'all';
		$post->unit_id = (int) get_post_meta( $post->ID, 'unit_id', true );
		$post->unit_title = ! empty( $post->unit_id ) ? get_the_title( $post->unit_id ) : __( 'All units', 'cp' );
		$post->unit_id = ! empty( $post->unit_id ) ? $post->unit_id : 'course';
		$post->unit_id = 'all' === $post->course_id ? 'course' : $post->unit_id;
		$post->url = $url.$post->post_name;
		$data[] = $post;
	}
	return $data;
}

/**
 * Get single discussion
 */
function coursepress_get_discussion() {
	$topic = get_query_var( 'topic' );
	if ( empty( $topic ) ) {
		return array();
	}
	$found_post = null;

	$posts = get_posts( array(
		'name' => $topic,
		'post_type' => 'discussions',
		'post_status' => 'publish',
		'posts_per_page' => 1,
	) );
	if ( $posts ) {
		$found_post = $posts[0];
	}

	return $found_post;
}

/**
 * Get categories assigned to a course.
 *
 * @param int $course_id Course ID
 *
 * @return array
 */
function coursepress_get_course_categories( $course_id ) {

	$cats = array();
	if ( empty( $course_id ) ) {
		return $cats;
	}

	$course_category = wp_get_object_terms( $course_id, 'course_category' );
	if ( ! empty( $course_category ) ) {
		foreach ( $course_category as $term ) {
			$cats[ $term->term_id ] = $term->name;
		}
	}

	return $cats;
}

/**
 * Get prerequisite of course.
 *
 * @param int $course_id Course ID
 *
 * @return array|bool
 */
function coursepress_get_enrollment_prerequisite( $course_id ) {

	if ( empty( $course_id ) ) {
		return array();
	}

	$courses = coursepress_course_get_setting( $course_id, 'enrollment_prerequisite', array() );
	if ( empty( $courses ) ) {
		return array();
	}

	$courses = (array) $courses;

	$courses = array_diff( $courses, array( $course_id ) );

	return $courses;
}
