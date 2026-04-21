<?php

namespace Smush\Core\Threads;

/**
 * TODO: use this in places where we are currently using mutex
 */
class Thread_Safe_Options {

	public function delete_option( $option_id ) {
		return $this->delete( $option_id );
	}

	public function delete_site_option( $option_id ) {
		return $this->delete( $option_id, true );
	}

	private function delete( $option_id, $site_option = false ) {
		global $wpdb;

		list( $table, $column ) = $this->get_table_columns( $site_option );

		return $wpdb->delete( $table, array(
			$column => $option_id,
		), '%s' );
	}

	public function get_option( $option_id, $default = false ) {
		return $this->get_value_from_db( $option_id, $default );
	}

	/**
	 * Thread safe version of get_site_option, queries the database directly to prevent use of cached values
	 *
	 * @param $option_id string
	 * @param $default
	 *
	 * @return false|mixed
	 */
	public function get_site_option( $option_id, $default = false ) {
		return $this->get_value_from_db( $option_id, $default, true );
	}

	private function get_value_from_db( $option_id, $default, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column, $key_column ) = $this->get_table_columns( $site_option );

		$row = $wpdb->get_row( $wpdb->prepare( "
			SELECT *
			FROM {$table}
			WHERE {$column} = %s
			ORDER BY {$key_column} ASC
			LIMIT 1
		", $option_id ) );

		if ( empty( $row->$value_column ) || ! is_object( $row ) ) {
			return $default;
		}

		$decoded = json_decode( $row->$value_column, true );
		if ( is_null( $decoded ) ) {
			return $default;
		}

		return $decoded;
	}

	public function add_data( $option_id, $key, $data ) {
		return $this->json_set_object( $option_id, $key, $data );
	}

	public function add_data_in_site_option( $option_id, $key, $data ) {
		return $this->json_set_object( $option_id, $key, $data, true );
	}

	public function remove_data( $option_id, $key ) {
		return $this->json_remove( $option_id, $key );
	}

	private function json_set_object( $option_id, $key, $data, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_object_option( $table, $column, $option_id, $value_column );

		$json_object = [];
		foreach ( $data as $data_key => $value ) {
			$json_object[] = $wpdb->prepare( is_int( $value ) ? "%s, %d" : "%s, %s", $data_key, $value );
		}
		$json_object_string = implode( ',', $json_object );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = JSON_SET({$value_column}, '$.\"$key\"', JSON_OBJECT({$json_object_string}))
			WHERE {$column} = '$option_id';
		" );
	}

	private function json_remove( $option_id, $key, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_object_option( $table, $column, $option_id, $value_column );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = JSON_REMOVE({$value_column}, '$.\"$key\"')
			WHERE {$column} = '$option_id';
		" );
	}

	public function append_to_array( $option_id, $values ) {
		return $this->json_array_append_scalars( $option_id, $values );
	}

	public function add_to_array_in_site_option( $option_id, $values ) {
		return $this->json_array_append_scalars( $option_id, $values, true );
	}

	/**
	 * Atomically append a single associative-array (object) to a JSON array option.
	 *
	 * Uses a single JSON_ARRAY_APPEND … CAST(? AS JSON) UPDATE so concurrent writers
	 * never overwrite each other's entries.
	 *
	 * @param string $option_id  WP option name.
	 * @param array  $object     Associative array to append.
	 *
	 * @return int|false Number of affected rows, or false on error.
	 */
	public function append_object_to_array( $option_id, $object ) {
		return $this->json_array_append_object( $option_id, $object );
	}

	public function append_object_to_array_in_site_option( $option_id, $object ) {
		return $this->json_array_append_object( $option_id, $object, true );
	}

	/**
	 * Overwrite the entire stored JSON array with the supplied objects.
	 *
	 * Unlike append_object_to_array(), this is not concurrent-write safe; it is
	 * intended for single-writer contexts such as trimming an oversized list at
	 * read time.
	 *
	 * @param string $option_id WP option name.
	 * @param array  $objects   Indexed array of associative arrays to store.
	 *
	 * @return int|false Number of affected rows, or false on DB error.
	 */
	public function replace_object_array( $option_id, $objects ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( false );

		$json_string  = wp_json_encode( array_values( $objects ) );
		$prepared_id  = $wpdb->prepare( '%s', $option_id );
		$prepared_val = $wpdb->prepare( '%s', $json_string );

		return $wpdb->query(
			"INSERT INTO {$table} (`{$column}`, `{$value_column}`)
			 VALUES ({$prepared_id}, {$prepared_val})
			 ON DUPLICATE KEY UPDATE `{$value_column}` = {$prepared_val}"
		);
	}

	private function json_array_append_object( $option_id, $object, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$this->initialize_json_array_option( $table, $column, $option_id, $value_column );

		$json_string = wp_json_encode( $object );

		// CAST(expr AS JSON) is not supported on MariaDB < 10.5.2. Using
		// JSON_EXTRACT(CONCAT('[', expr, ']'), '$[0]') achieves the same result
		// and works on MySQL 5.7+ and MariaDB 10.2+.
		$prepared_json = $wpdb->prepare( '%s', $json_string );
		$prepared_id   = $wpdb->prepare( '%s', $option_id );

		return $wpdb->query(
			"UPDATE {$table}
			 SET {$value_column} = JSON_ARRAY_APPEND({$value_column}, '$', JSON_EXTRACT(CONCAT('[', {$prepared_json}, ']'), '$[0]'))
			 WHERE {$column} = {$prepared_id}"
		);
	}

	private function json_array_append_scalars( $option_id, $values, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_array_option( $table, $column, $option_id, $value_column );

		$json_values = [];
		foreach ( $values as $value ) {
			$json_values[] = $wpdb->prepare( is_int( $value ) ? "'$', %d" : "'$', %s", $value );
		}
		$json_values_string = implode( ',', $json_values );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = JSON_ARRAY_APPEND({$value_column}, {$json_values_string})
			WHERE {$column} = '$option_id';
		" );
	}

	public function remove_from_array( $option_id, $value ) {
		return $this->json_array_remove_scalars( $option_id, $value );
	}

	public function remove_from_array_in_site_option( $option_id, $value ) {
		return $this->json_array_remove_scalars( $option_id, $value, true );
	}

	private function json_array_remove_scalars( $option_id, $value, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_array_option( $table, $column, $option_id, $value_column );

		$json_value = $wpdb->prepare( is_int( $value ) ? "%d" : "%s", $value );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = IF(
			    JSON_SEARCH({$value_column}, 'one', {$json_value}, NULL, '$') IS NOT NULL,
			    JSON_REMOVE({$value_column}, JSON_UNQUOTE(JSON_SEARCH({$value_column}, 'one', {$json_value}, NULL, '$'))),
			    {$value_column}
			)
			WHERE {$column} = '$option_id';
		" );
	}

	public function set_values( $option_id, $associative_array ) {
		return $this->set_json_values( $option_id, $associative_array );
	}

	public function set_values_in_site_option( $option_id, $associative_array ) {
		return $this->set_json_values( $option_id, $associative_array, true );
	}

	public function get_value( $option_id, $key, $default = false ) {
		$values = $this->get_option( $option_id );
		$values = empty( $values ) ? array() : $values;

		return isset( $values[ $key ] ) ? $values[ $key ] : $default;
	}

	private function set_json_values( $option_id, $associative_array, $site_option = false ) {
		return $this->run_json_set_query( $option_id, $associative_array, $site_option, function ( $value_column, $key, $value ) {
			global $wpdb;

			return $wpdb->prepare( "%s, %s", "$.\"$key\"", $value );
		} );
	}

	public function increment_values( $option_id, $keys ) {
		return $this->increment_json_values( $option_id, $keys );
	}

	public function increment_values_in_site_option( $option_id, $keys ) {
		return $this->increment_json_values( $option_id, $keys, true );
	}

	private function increment_json_values( $option_id, $keys, $site_option = false ) {
		$values = [];
		foreach ( $keys as $key ) {
			$values[ $key ] = 1;
		}

		return $this->add_to_values( $option_id, $values, $site_option );
	}

	public function add_to_values( $option_id, $values, $site_option = false ) {
		return $this->run_json_set_query( $option_id, $values, $site_option, function ( $value_column, $key, $addend ) {
			global $wpdb;

			return $wpdb->prepare( "%s, CAST(JSON_UNQUOTE(IFNULL(JSON_EXTRACT($value_column, %s), 0)) + %d AS SIGNED)", "$.\"$key\"", "$.\"$key\"", $addend );
		} );
	}

	public function decrement_values( $option_id, $keys ) {
		return $this->decrement_json_values( $option_id, $keys );
	}

	public function decrement_values_in_site_option( $option_id, $keys ) {
		return $this->decrement_json_values( $option_id, $keys, true );
	}

	private function decrement_json_values( $option_id, $keys, $site_option = false ) {
		$values = [];
		foreach ( $keys as $key ) {
			$values[ $key ] = 1;
		}

		return $this->subtract_from_values( $option_id, $values, $site_option );
	}

	public function subtract_from_values( $option_id, $values, $site_option = false ) {
		return $this->run_json_set_query( $option_id, $values, $site_option, function ( $value_column, $key, $subtrahend ) {
			global $wpdb;

			return $wpdb->prepare( "%s, CAST(JSON_UNQUOTE(IFNULL(JSON_EXTRACT($value_column, %s), 0)) - %d AS SIGNED)", "$.\"$key\"", "$.\"$key\"", $subtrahend );
		} );
	}

	/**
	 * @param $site_option
	 *
	 * @return array
	 */
	private function get_table_columns( $site_option ) {
		global $wpdb;

		$table        = $wpdb->options;
		$column       = 'option_name';
		$value_column = 'option_value';
		$key_column   = 'option_id';

		if ( $site_option && is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$value_column = 'meta_value';
			$key_column   = 'meta_id';
		}

		return array( $table, $column, $value_column, $key_column );
	}

	private function run_json_set_query( $option_id, $values, $site_option, $prepare_single_value_query ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_object_option( $table, $column, $option_id, $value_column );

		$set_values = [];
		foreach ( $values as $key => $value ) {
			$set_values[] = call_user_func( $prepare_single_value_query, $value_column, $key, $value );
		}
		$set = implode( ', ', $set_values );

		$query = "
				UPDATE {$table}
				SET $value_column = JSON_SET($value_column, $set)
				WHERE {$column} = %s;
		";
		return $wpdb->query( $wpdb->prepare( $query, $option_id ) );
	}

	private function initialize_json_object_option( $table, $column, $option_id, $value_column ) {
		global $wpdb;

		return $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (`{$column}`, `{$value_column}`)
			 VALUES (%s, '{}')
			 ON DUPLICATE KEY UPDATE
			     `{$value_column}` = IF(JSON_VALID(`{$value_column}`), `{$value_column}`, '{}')",
			$option_id
		) );
	}

	private function initialize_json_array_option( $table, $column, $option_id, $value_column ) {
		global $wpdb;

		// Insert a fresh JSON array if the row doesn't exist yet.
		// If it already exists but holds a non-JSON value (e.g. a PHP-serialised array
		// written by the old update_option() path), overwrite it with an empty JSON array
		// so that subsequent JSON_ARRAY_APPEND calls don't fail.
		return $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (`{$column}`, `{$value_column}`)
			 VALUES (%s, '[]')
			 ON DUPLICATE KEY UPDATE
			     `{$value_column}` = IF(JSON_VALID(`{$value_column}`), `{$value_column}`, '[]')",
			$option_id
		) );
	}
}
