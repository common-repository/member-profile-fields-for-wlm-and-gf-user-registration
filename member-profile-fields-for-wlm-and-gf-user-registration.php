<?php
/**
 * Plugin Name:		Member Profile Fields for WishList Member and Gravity Forms User Registration Add-On
 * Description:		Allows setting WishList Member Fields when users are automatically created using Gravity Forms User Registration Add-On.
 * Version:			1.03
 * Author:			AoD Technologies LLC
 * Author URI:		https://aod-tech.com/
 * License:			GPL-3.0+
 * License URI:		https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:		member-profile-fields-for-wlm-and-gf-user-registration
 * Domain Path:		/languages
 *
 * Member Profile Fields for WishList Member and Gravity Forms User Registration Add-On Plugin
 * Copyright (C) 2020 - 2024 AoD Technologies LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace MemberProfileFieldsForWLMAndGFUserRegistration;

if ( !defined( 'ABSPATH' ) ) {
	die();
}

class Plugin {
	private $gf_user_registration = null;
	private $update_user_id = null;

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	public function load_text_domain() {
		load_plugin_textdomain( 'member-profile-fields-for-wlm-and-gf-user-registration', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	public function init() {
		if ( !class_exists( '\\GF_User_Registration' ) || !function_exists( '\\wlmapi_update_member' ) ) {
			// Gravity Forms User Registration Add-On and/or WishList Member not activated, so we do nothing
			return;
		}

		$this->gf_user_registration = \GF_User_Registration::get_instance();

		add_filter( 'gform_userregistration_feed_settings_fields', array( $this, 'filter_feed_settings_fields' ) );
		add_filter( 'gform_user_registration_update_user_id', array( $this, 'set_filter_update_user_id_for_form_id' ), 10, 4 );
		add_filter( 'gform_user_registration_user_data_pre_populate', array( $this, 'filter_user_data_pre_populate' ), 10, 3 );

		add_action( 'gform_user_registered', array( $this, 'update_wlm_data' ), 5, 3 );
		add_action( 'gform_user_updated',    array( $this, 'update_wlm_data' ), 5, 3 );
	}

	public function filter_feed_settings_fields( $fields ) {
		$num_fields = count( $fields );
		if ( $num_fields === 0 ) {
			return $fields;
		}

		return array_merge(
			array_slice( $fields, 0, $num_fields - 1 ),
			array(
				'wlm_meta' => array(
					'title'       => esc_html__( 'WishList Member Profile', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
					'description' => '',
					'dependency'  => array(
						'field'   => 'feedType',
						'values'  => '_notempty_'
					),
					'fields'      => array(
						array(
							'name'      => 'wlmMeta',
							'label'     => '',
							'type'      => 'dynamic_field_map',
							'field_map' => array(
								array(
									'label' => esc_html__( 'Select WishList Member Field', 'wlmprofileforgravityformsuserregistration' ),
									'value' => ''
								),
								array(
									'label'   => esc_html__( 'Standard User Fields', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
									'choices' => array(
										array(
											'label' => esc_html__( 'Company', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
											'value' => 'company'
										),
										array(
											'label' => esc_html__( 'Street Address', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
											'value' => 'address1'
										),
										array(
											'label' => esc_html__( 'Address Line 2', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
											'value' => 'address2'
										),
										array(
											'label' => esc_html__( 'City', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
											'value' => 'city'
										),
										array(
											'label' => esc_html__( 'State / Province', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
											'value' => 'state'
										),
										array(
											'label' => esc_html__( 'ZIP / Postal Code', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
											'value' => 'zip'
										),
										array(
											'label' => esc_html__( 'Country', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
											'value' => 'country'
										)
									)
								),
								array(
									'label' => esc_html__( 'Add Custom User Field', 'member-profile-fields-for-wlm-and-gf-user-registration' ),
									'value' => 'gf_custom'
								)
							),
							'class'     => 'medium'
						)
					)
				)
			),
			array_slice( $fields, $num_fields - 1 )
		);
	}

	public function set_filter_update_user_id_for_form_id( $user_id, $entry, $form, $feed ) {
		remove_filter( 'gform_user_registration_update_user_id', array( $this, 'set_filter_update_user_id_for_form_id' ), 10, 4 );

		add_filter( 'gform_user_registration_update_user_id_' . \rgar( $form, 'id' ), array( $this, 'filter_update_user_id' ), PHP_INT_MAX, 4);

		return $user_id;
	}

	public function filter_update_user_id( $user_id, $entry, $form, $feed ) {
		remove_filter( 'gform_user_registration_update_user_id_' . \rgar( $form, 'id' ), array( $this, 'filter_update_user_id' ), PHP_INT_MAX, 4);

		$this->update_user_id = $user_id;

		return $user_id;
	}

	public function filter_user_data_pre_populate( $mapped_fields, $form, $feed ) {
		if ( empty( $this->update_user_id ) ) {
			return $mapped_fields;
		}

		$meta = $this->get_wlm_meta( $feed );

		if ( empty( $meta ) ) {
			return $mapped_fields;
		}

		$get_member_response = \wlmapi_get_member( $this->update_user_id );
		if (
			$get_member_response['success'] === 0 ||
			empty( $get_member_response['member'] ) ||
			$get_member_response['member'][0]['ID'] === null
		) {
			return $mapped_fields;
		}

		$user_info = $get_member_response['member'][0]['UserInfo'];

		foreach ( $meta as $wlm_field_id => $gf_field_id ) {
			if ( empty( $wlm_field_id ) || empty( $gf_field_id ) ) {
				continue;
			}

			$value = null;
			if ( in_array( $wlm_field_id, array( 'company', 'address1', 'address2', 'city', 'state', 'zip', 'country' ) ) ) {
				$value = $user_info['wpm_useraddress'][$wlm_field_id];
			} else {
				if ( strpos( $wlm_field_id, 'custom_' ) !== 0 ) {
					$wlm_field_id = 'custom_' . $wlm_field_id;
				}

				$value = $user_info[$wlm_field_id];
			}

			if ( empty( $value ) ) {
				continue;
			}
	
			$mapped_fields[(string) $gf_field_id] = $value;
		}

		return $mapped_fields;
	}

	public function update_wlm_data( $user_id, $feed, $entry ) {
		$wlm_data = $this->prepare_wlm_data( $user_id, $feed, $entry );

		$this->insert_wlm_data( $user_id, $wlm_data );
	}

	private function insert_wlm_data( $user_id, $wlm_data ) {
		if ( empty( $wlm_data ) ) {
			return;
		}

		\wlmapi_update_member( $user_id, $wlm_data );
	}

	private function prepare_wlm_data( $user_id, $feed, $entry ) {
		$wlm_data = array();
		$meta     = $this->get_wlm_meta( $feed );

		if ( empty( $meta ) ) {
			return $wlm_data;
		}

		$user_info = get_userdata( $user_id );
		if ( $user_info === false ) {
			return $wlm_data;
		}

		$form = \GFFormsModel::get_form_meta( $entry['form_id'] );

		foreach ( $meta as $wlm_field_id => $gf_field_id ) {
			if ( empty( $wlm_field_id ) || empty( $gf_field_id ) ) {
				continue;
			}

			$meta_value = $this->gf_user_registration->get_meta_value( $wlm_field_id, $meta, $form, $entry );

			if ( !in_array( $wlm_field_id, array( 'company', 'address1', 'address2', 'city', 'state', 'zip', 'country' ) ) && strpos( $wlm_field_id, 'custom_' ) !== 0 ) {
				$wlm_field_id = 'custom_' . $wlm_field_id;
			}

			$wlm_data[$wlm_field_id] = $meta_value;
		}

		$wlm_data['user_login'] = $user_info->user_login;
		$wlm_data['user_email'] = $user_info->user_email;

		return $wlm_data;
	}

	private function get_wlm_meta( $feed ) {
		return $this->gf_user_registration->prepare_dynamic_meta( \rgars( $feed, 'meta/wlmMeta' ) );
	}
}

new Plugin();
