<?php
/**
 * Class CoursePress_Course
 *
 * @since 3.0
 * @package CoursePress
 */
class CoursePress_Course extends CoursePress_Utility {
	/**
	 * CoursePress_Course constructor.
	 *
	 * @param int|WP_Post $course
	 */
	public function __construct( $course ) {
		if ( ! $course instanceof WP_Post ) {
			$course = get_post( (int) $course );
		}

		if ( ! $course instanceof WP_Post
		     || $course->post_type != 'course' ) {
			return $this->wp_error();
		}

		foreach ( $course as $key => $value ) {
			$this->__set( $key, $value );
		}

		// Set course meta
		$this->setUpCourseMetas();
	}

	function wp_error() {
		return new WP_Error( 'wrong_param', __( 'Invalid course ID!', 'cp' ) );
	}

	function setUpCourseMetas() {
		$settings = $this->get_settings();
		$date_format = coursepress_get_option( 'date_format' );
		$time_now = current_time( 'timestamp' );
		$date_keys = array( 'course_start_date', 'course_end_date', 'enrollment_start_date', 'enrollment_end_date' );

		foreach ( $settings as $key => $value ) {
			if ( in_array( $key, $date_keys ) && ! empty( $value ) ) {
				$timestamp = strtotime( $value, $time_now );
				$value = date_i18n( $date_format, $timestamp );

				// Add timestamp info
				$this->__set( $key . '_timestamp', $timestamp );
			}

			// Legacy fixes
			if ( 'enrollment_type' == $key && 'anyone' == $value )
				$value = 'registered';

			$this->__set( $key, $value );
		}

		// Legacy: fix course_type meta
		if ( ! $this->__get( 'with_modules' ) )
			$this->__set( 'with_modules', true );
		if ( ! $this->__get( 'course_type' ) )
			$this->__set( 'course_type', 'auto-moderated' );
	}

	function get_settings() {
		$settings = get_post_meta( $this->ID, 'course_settings', true );
		return $settings;
	}

	/**
	 * Check if the course has already started.
	 *
	 * @return bool
	 */
	function has_course_started() {
		$time_now = $this->date_time_now();
		$openEnded = $this->__get( 'course_open_ended' );
		$start_date = $this->__get( 'course_start_date_timestamp' );

		if ( empty( $openEnded )
		     && $start_date > 0
		     && $start_date > $time_now )
			return false;

		return true;
	}

	/**
	 * Check if the course is no longer open.
	 *
	 * @return bool
	 */
	function has_course_ended() {
		$time_now = $this->date_time_now();
		$openEnded = $this->__get( 'course_open_ended' );
		$end_date = $this->__get( 'course_end_date_timestamp' );

		if ( empty( $openEnded )
		     && $end_date > 0
		     && $end_date < $time_now )
			return true;

		return false;
	}

	/**
	 * Check if the course is available
	 *
	 * @return bool
	 */
	function is_available() {
		$is_available = $this->has_course_started();

		if ( $is_available ) {
			// Check if the course hasn't ended yet
			if ( $this->has_course_ended() )
				$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Check if enrollment is open.
	 *
	 * @return bool
	 */
	function has_enrollment_started() {
		$time_now = $this->date_time_now();
		$enrollment_open = $this->__get( 'enrollment_open_ended' );
		$start_date = $this->__get( 'enrollment_start_date_timestamp' );

		if ( empty( $enrollment_open )
		     && $start_date > 0
		     && $start_date > $time_now )
			return false;

		return true;
	}

	/**
	 * Check if enrollment has closed.
	 *
	 * @return bool
	 */
	function has_enrollment_ended() {
		$time_now = $this->date_time_now();
		$enrollment_open = $this->__get( 'enrollment_open_ended' );
		$end_date = $this->__get( 'enrollment_end_date_timestamp' );

		if ( empty( $enrollment_open )
		     && $end_date > 0
		     && $end_date < $time_now )
			return true;

		return false;
	}

	/**
	 * Check if user can enroll to the course.
	 *
	 * @return bool
	 */
	function user_can_enroll() {
		$available = $this->is_available();

		if ( $available ) {
			// Check if enrollment has started
			$available = $this->has_enrollment_started();

			// Check if enrollment already ended
			if ( $available && $this->has_course_ended() )
				$available = false;
		}

		return $available;
	}

	private function _get_instructors() {
		$instructor_ids = get_post_meta( $this->ID, 'instructor' );
		$instructor_ids = array_filter( $instructor_ids );

		if ( ! empty( $instructor_ids ) )
			return $instructor_ids;

		// Legacy call
		// @todo: Delete this meta
		$instructor_ids = get_post_meta( $this->ID, 'instructors', true );

		if ( ! empty( $instructor_ids ) )
			foreach ( $instructor_ids as $instructor_id )
				coursepress_add_instructor( $instructor_id, $this->ID );

		return $instructor_ids;
	}

	/**
	 * Count total number of course instructors.
	 *
	 * @return int
	 */
	function count_instructors() {
		return count( $this->_get_instructors() );
	}

	/**
	 * Get course instructors.
	 *
	 * @return array An array of WP_User object on success.
	 */
	function get_instructors() {
		$instructors = array();
		$instructor_ids = $this->_get_instructors();

		if ( ! empty( $instructor_ids ) )
			foreach ( $instructor_ids as $instructor_id )
				$instructors[ $instructor_id ] = new CoursePress_User( $instructor_id );

		return $instructors;
	}

	private function _get_facilitators() {
		$facilitator_ids = get_post_meta( $this->ID, 'facilitator' );

		if ( is_array( $facilitator_ids ) && ! empty( $facilitator_ids ) )
			return array_unique( array_filter( $facilitator_ids ) );

		return array();
	}

	/**
	 * Count the total number of course facilitators.
	 *
	 * @return int
	 */
	function count_facilitators() {
		return count( $this->_get_facilitators() );
	}

	/**
	 * Get course facilitators.
	 *
	 * @return array of WP_User object
	 */
	function get_facilitators() {
		$facilitator_ids = $this->_get_facilitators();

		return array_map( 'get_userdata', $facilitator_ids );
	}

	private function _get_students() {
		$student_ids = get_post_meta( $this->ID, 'student' );

		if ( is_array( $student_ids ) && ! empty( $student_ids ) )
			return array_unique( array_filter( $student_ids ) );

		return array();
	}

	/**
	 * Count total number of students in a course.
	 *
	 * @return int
	 */
	function count_students() {
		return count( $this->_get_students() );
	}

	/**
	 * Get course students
	 *
	 * @return array of CoursePress_User object
	 */
	function get_students() {
		$students = array();
		$student_ids = $this->_get_students();

		if ( ! empty( $student_ids ) ) {
			foreach ( $student_ids as $student_id ) {
				$students[ $student_id ] = new CoursePress_User( $student_id );
			}
		}

		return $students;
	}

	function count_certified_students() {
		// @todo: count certified students here
		return 0;
	}

	/**
	 * Get an array of categories of the course.
	 *
	 * @return array
	 */
	function get_category() {
		$course_category = wp_get_object_terms( $this->ID, 'course_category' );
		$cats = array();

		if ( ! empty( $course_category ) )
			foreach ( $course_category as $term )
				$cats[ $term->term_id ] = $term->name;

		return $cats;
	}

	function get_units( $publish = true ) {
		$args = array(
			'post_type'      => 'unit',
			'post_status'    => $publish ? 'publish' : 'any',
			'post_parent'    => $this->__get( 'ID' ),
			'posts_per_page' => - 1, // Units are often retrieve all at once
			'suppress_filters' => true,
			'orderby' => 'menu_order',
			'order' => 'ASC',
		);

		$units = array();
		$results = get_posts( $args );

		if ( ! empty( $results ) ) {
			foreach ( $results as $unit ) {
				$units[] = new CoursePress_Unit( $unit );
			}
		}

		return $units;
	}
}