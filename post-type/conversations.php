<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class Disciple_Tools_Conversations_Base
 * Load the core post type hooks into the Disciple.Tools system
 */
class Disciple_Tools_Conversations_Base extends DT_Module_Base {

    public $post_type = 'conversations';
    public $module = 'conversations_base';
    public $single_name = 'Conversation';
    public $plural_name = 'Conversations';
    public static function post_type(){
        return 'conversations';
    }

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        parent::__construct();
        if ( !self::check_enabled_and_prerequisites() ){
            return;
        }

        //setup post type
        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_filter( 'dt_set_roles_and_permissions', [ $this, 'dt_set_roles_and_permissions' ], 20, 1 ); //after contacts

        //setup tiles and fields
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 200, 2 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 20, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        add_filter( 'dt_get_post_type_settings', [ $this, 'dt_get_post_type_settings' ], 20, 2 );
        add_filter( 'dt_comments_additional_sections', [ $this, 'add_comment_section' ], 10, 2 );


        // hooks
        add_action( 'post_connection_removed', [ $this, 'post_connection_removed' ], 10, 4 );
        add_action( 'post_connection_added', [ $this, 'post_connection_added' ], 10, 4 );
        add_filter( 'dt_create_post_args', [ $this, 'dt_create_post_args' ], 10, 3 );
        add_filter( 'dt_create_post_check_proceed', [ $this, 'dt_create_post_check_proceed' ], 10, 3 );
        add_filter( 'dt_update_post_check_proceed', [ $this, 'dt_update_post_check_proceed' ], 10, 3 );
        add_filter( 'dt_post_update_fields', [ $this, 'dt_post_update_fields' ], 10, 3 );
        add_filter( 'dt_post_create_fields', [ $this, 'dt_post_create_fields' ], 10, 2 );
        add_action( 'dt_post_created', [ $this, 'dt_post_created' ], 10, 3 );
//        add_action( 'dt_post_updated', [ $this, 'dt_post_updated' ], 10, 5 );

        add_action( 'dt_comment_created', [ $this, 'dt_comment_created' ], 10, 4 );
        add_filter( 'dt_filter_post_comments', [ $this, 'dt_filter_post_comments' ], 10, 3 );

//        add_action( 'dt_record_after_details_section', [ $this, 'dt_record_after_details_section' ], 10, 2 );

        //comments
//        add_filter( 'dt_filter_post_comments', [ $this, 'dt_filter_post_comments' ], 10, 3 );
    }

    public function after_setup_theme(){
        $this->single_name = __( 'Conversation', 'disciple-tools-conversations' );
        $this->plural_name = __( 'Conversations', 'disciple-tools-conversations' );

        if ( class_exists( 'Disciple_Tools_Post_Type_Template' ) ) {
            new Disciple_Tools_Post_Type_Template( $this->post_type, $this->single_name, $this->plural_name );
        }
    }

      /**
     * Set the singular and plural translations for this post types settings
     * The add_filter is set onto a higher priority than the one in Disciple_tools_Post_Type_Template
     * so as to enable localisation changes. Otherwise the system translation passed in to the custom post type
     * will prevail.
     */
    public function dt_get_post_type_settings( $settings, $post_type ){
        if ( $post_type === $this->post_type ){
            $settings['label_singular'] = __( 'Conversation', 'disciple-tools-conversations' );
            $settings['label_plural'] = __( 'Conversations', 'disciple-tools-conversations' );
        }
        return $settings;
    }

    // @todo
    public function dt_set_roles_and_permissions( $expected_roles ){

        // if the user can access contacts they also can access conversations
        foreach ( $expected_roles as $role => $role_value ){
            if ( isset( $expected_roles[$role]['permissions']['access_contacts'] ) && $expected_roles[$role]['permissions']['access_contacts'] ){
                $expected_roles[$role]['permissions']['access_' . $this->post_type ] = true;
                $expected_roles[$role]['permissions']['create_' . $this->post_type] = true;
                $expected_roles[$role]['permissions']['update_' . $this->post_type] = true;
            }
        }
        // if the user can access all contacts they also can access all conversations
        foreach ( $expected_roles as $role => $role_value ){
            if ( isset( $expected_roles[$role]['permissions']['view_any_contacts'] ) && $expected_roles[$role]['permissions']['view_any_contacts'] ){
                $expected_roles[$role]['permissions']['view_any_' . $this->post_type ] = true;
                $expected_roles[$role]['permissions']['update_any_' . $this->post_type] = true;
            }
        }

        if ( isset( $expected_roles['dt_admin'] ) ){
            $expected_roles['dt_admin']['permissions']['view_any_'.$this->post_type ] = true;
            $expected_roles['dt_admin']['permissions']['update_any_'.$this->post_type ] = true;
        }
        if ( isset( $expected_roles['administrator'] ) ){
            $expected_roles['administrator']['permissions']['view_any_'.$this->post_type ] = true;
            $expected_roles['administrator']['permissions']['update_any_'.$this->post_type ] = true;
            $expected_roles['administrator']['permissions']['delete_any_'.$this->post_type ] = true;
        }

        return $expected_roles;
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === $this->post_type ){

            $fields['name']['tile'] = 'status';
            $fields['name']['name'] = 'Unique Identifier';

            $fields['type'] = [
                'name'        => __( 'Type', 'disciple-tools-conversations' ),
                'description' => __( 'Select the type of conversation.', 'disciple-tools-conversations' ),
                'type'        => 'key_select',
                'default'     => [
                    'email'   => [
                        'label' => __( 'Email', 'disciple-tools-conversations' ),
                        'description' => __( 'Email conversation', 'disciple-tools-conversations' ),
                    ],
                    'phone'   => [
                        'label' => __( 'Phone', 'disciple-tools-conversations' ),
                        'description' => __( 'SMS, Whatsapp, etc', 'disciple-tools-conversations' ),
                    ],
                    'facebook' => [
                        'label' => __( 'Facebook', 'disciple-tools-conversations' ),
                        'description' => __( 'Facebook conversation', 'disciple-tools-conversations' ),
                    ],
                ],
                'tile'     => 'status',
                'font-icon' => 'mdi mdi-arrow-decision',
                'show_in_table' => 5,
                'select_cannot_be_empty' => true,
            ];


            $fields['status'] = [
                'name'        => __( 'Status', 'crm-emails' ),
                'description' => __( 'Set the current status.', 'crm-emails' ),
                'type'        => 'key_select',
                'default'     => [
                    'unverified' => [
                        'label' => __( 'Not Verified', 'crm-emails' ),
                        'color' => '#FF9800'
                    ],
                    'verified' => [
                        'label' => __( 'Verified', 'crm-emails' ),
                        'color' => '#4CAF50'
                    ],
                    'unsubscribed'   => [
                        'label' => __( 'Unsubscribed', 'crm-emails' ),
                        'color' => '#F43636'
                    ],
                    'blocked'   => [
                        'label' => __( 'Do not Email', 'crm-emails' ),
                        'color' => '#F43636'
                    ],
                ],
                'tile'     => 'status',
                'font-icon' => 'mdi mdi-list-status',
                'default_color' => '#FFFFFF',
                'show_in_table' => 10,
            ];

            $fields['assigned_to'] = [
               'name'        => __( 'Assigned To', 'disciple-tools-conversations' ),
               'description' => __( 'Select the main person who is responsible for reporting on this record.', 'disciple-tools-conversations' ),
               'type'        => 'user_select',
               'default'     => '',
               'tile' => 'status',
               'icon' => get_template_directory_uri() . '/dt-assets/images/assigned-to.svg',
               'show_in_table' => 16,
            ];

            $fields['contacts'] = [
                'name' => __( 'Contacts', 'disciple-tools-conversations' ),
                'description' => '',
                'type' => 'connection',
                'post_type' => 'contacts',
                'p2p_direction' => 'to',
                'p2p_key' => $this->post_type.'_to_contacts',
                'tile' => 'status',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-type.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-contact.svg',
                'show_in_table' => 35
            ];

            //first name
            $fields['first_name'] = [
                'name'        => __( 'First Name', 'disciple-tools-conversations' ),
                'description' => __( 'First Name', 'disciple-tools-conversations' ),
                'type'        => 'text',
                'tile'        => 'details',
                'show_in_table' => 20,
            ];
            //last name
            $fields['last_name'] = [
                'name'        => __( 'Last Name', 'disciple-tools-conversations' ),
                'description' => __( 'Last Name', 'disciple-tools-conversations' ),
                'type'        => 'text',
                'tile'        => 'details',
                'show_in_table' => 25,
            ];
            //sources
            $fields['sources'] = [
                'name'        => __( 'Source', 'disciple-tools-conversations' ),
                'description' => __( 'Source of the conversation', 'disciple-tools-conversations' ),
                'type'        => 'tags',
                'tile'        => 'details',
                'show_in_table' => 30,
            ];
            $fields['PageID'] = [
                'name'        => __( 'Social Media Page ID', 'disciple-tools-conversations' ),
                'description' => __( 'Social Media Page ID', 'disciple-tools-conversations' ),
                'type'        => 'text',
                'tile'        => 'details',
                'show_in_table' => 40,
            ];
            $fields['profile_pic'] = [
                'name'        => __( 'Profile Picture', 'disciple-tools-conversations' ),
                'description' => __( 'Profile Picture', 'disciple-tools-conversations' ),
                'type'        => 'image',
                'tile'        => 'details',
                'show_in_table' => 45,
            ];
        }

        return $fields;
    }

    public function dt_details_additional_tiles( $tiles, $post_type = '' ){

        $tiles['conversation_tile'] = [
            'label' => __( 'Conversation', 'disciple-tools-prayer-campaigns' ),
            'hidden' => false,
        ];

        return $tiles;
    }
    public function add_comment_section( $sections, $post_type ){
        if ( $post_type === 'conversations' || $post_type === 'contacts' ){
            $sections[] = [
                'key' => 'facebook',
                'label' => __( 'Facebook Conversation', 'disciple-tools-facebook' )
            ];
            $sections[] = [
                'key' => 'email',
                'label' => __( 'Email Conversation', 'disciple-tools-facebook' )
            ];
            $sections[] = [
                'key' => 'whatsapp',
                'label' => __( 'WhatsApp Conversation', 'disciple-tools-facebook' )
            ];
            $sections[] = [
                'key' => 'sms',
                'label' => __( 'SMS Conversation', 'disciple-tools-facebook' )
            ];
        }
        return $sections;
    }

    public function dt_filter_post_comments( $comments, $post_type, $post_id ){
        if ( $post_type === 'conversations' ){
            foreach ( $comments as &$comment ){
                if ( !empty( $comment['gravatar'] ) ){
                    continue;
                }
                if ( $comment['comment_type'] === 'whatsapp' ){
                    $comment['gravatar'] = get_template_directory_uri() . '/dt-assets/images/whatsapp.svg';
                }
                if ( $comment['comment_type'] === 'email' ){
                    $comment['gravatar'] = get_template_directory_uri() . '/dt-assets/images/email.svg';
                }
                if ( $comment['comment_type'] === 'sms' ){
                    $comment['gravatar'] = get_template_directory_uri() . '/dt-assets/images/social-media.svg';
                }
            }
        }
        return $comments;
    }

    public function dt_details_additional_section( $section, $post_type ){

        if ( $post_type === $this->post_type && $section === 'conversation_tile' ) {
            $fields = DT_Posts::get_post_field_settings( $post_type );
            $post = DT_Posts::get_post( $this->post_type, get_the_ID() );
            $post_comments = DT_Posts::get_post_comments( $post_type, $post['ID'] );
            $social_mediator_url = get_option( 'disciple_tools_conversations_social_mediator_url' );
            ?>
            <div class="section-subheader">
                <div class="smm-conversation-list">
                    <smm-chat-window convoid=<?php echo esc_attr( wp_json_encode( get_the_ID() ) ) ?> userid=<?php echo esc_attr( get_current_user_id() ) ?> platform=<?php echo esc_attr( $post['sources'][0] ) ?> conversation='<?php echo esc_attr( wp_json_encode( $post ) ) ?>' conversation_messages='<?php echo esc_attr( wp_json_encode( $post_comments ) )?>' pageid='<?php echo esc_attr( $post['PageID'] ); ?>' socketurl="<?php echo esc_attr( $social_mediator_url )?>"></smm-chat-window>
                </div>
            </div>

        <?php }
    }


    /**
     * HOOKS
     */

    /**
     * Make sure only one post can be created with the same phone, email, tec
     * @param array $args
     * @param string $post_type
     * @return array
     */
    public function dt_create_post_args( array $args, string $post_type ){
        if ( $post_type === $this->post_type ){
            $args['check_for_duplicates'] = [ 'name', 'title' ];
        }
        return $args;
    }

    /**
     * Make sure conversation post has a type
     * @param boolean $proceed
     * @param array $fields
     * @param string $post_type
     * @return bool|WP_Error
     */
    public function dt_create_post_check_proceed( bool $proceed, array $fields, string $post_type ){
        if ( $post_type === $this->post_type ){
            if ( !isset( $fields['type'] ) ){
                return new WP_Error( 400, 'Handle Type is required', [ 'function' => __METHOD__ ] );
            }
        }
        return $proceed;
    }

    /**
     * Make sure communication handles can not be changed.
     * Instead a new conversation should be created.
     * @param boolean $proceed
     * @param array $fields
     * @param string $post_type
     * @return bool|WP_Error
     */
    public function dt_update_post_check_proceed( bool $proceed, array $fields, string $post_type ){
        if ( $post_type === $this->post_type ){
            $name = $fields['title'] ?? $fields['name'] ?? '';
            if ( !empty( $name ) ){
                return new WP_Error( 400, 'Cannot update communication handles', [ 'function' => __METHOD__ ] );
            }
        }
        return $proceed;
    }

    public function post_connection_added( $post_type, $post_id, $field_key, $value ){
    }

    //action when a post connection is removed during create or update
    public function post_connection_removed( $post_type, $post_id, $field_key, $value ){
//        if ( $post_type === $this->post_type ){
//            // execute your code here, if connection removed
//        }
    }

    //filter at the start of post update
    public function dt_post_update_fields( $fields, $post_type, $post_id ){
//        if ( $post_type === $this->post_type ){
//            // execute your code here
//        }
        return $fields;
    }


    //filter when a comment is created
    public function dt_comment_created( $post_type, $post_id, $comment_id, $type ){
        if ( $post_type === $this->post_type ){
            // get the post and comment
            $post = DT_Posts::get_post( $post_type, $post_id );
            //using the standard WP comment insteaed of getting all DT comments with DT_Posts::get_post_comments and filtering for the correct one. If we need to get the comment meta we can use get_comment_meta( $comment_id, $key, $single )
            $comment = get_comment( $comment_id );
            $comment_meta = get_comment_meta( $comment_id );

            //Check if the comment is an inbound message if so don't send it to the social mediator server
            if ( isset( $comment_meta['disciple_tools_conversations_inbound_message'] ) ){
                return;
            }
            //the conversation UID is currently the name of the conversation but that should be changed TODO: change the conversation UID to store in a field other than name so it doesn't get changed.
            $conversation_uid = $post['name'];
            //send the message to the social mediator server
            $response = DT_Conversations_API::send_message( $conversation_uid, $type, $comment->comment_content );

            //if the response is an error then log it if success then add the comment meta to the comment
            if ( is_wp_error( $response ) ){
                dt_write_log( 'Error sending message to social mediator server: ' . $response->get_error_message() );
            } else {
                //Adds a comment meta to the comment to show that the message was sent to the social mediator server
                add_comment_meta( $comment_id, 'disciple_tools_conversations_message_sent', true, true );
            }
        }
    }

    // filter at the start of post creation
    public function dt_post_create_fields( $fields, $post_type ){
        if ( $post_type === $this->post_type ){
            $handle_types = DT_Conversations_API::get_handles();
            $handle_type = $handle_types[$fields['type']] ?? null;
            $name = $fields['title'] ?? $fields['name'] ?? '';
            if ( $handle_type && isset( $handle_type['convert_to_lowercase'] ) && $handle_type['convert_to_lowercase'] ){
                $fields['name'] = strtolower( $name );
            }
            //remove name whitespace
            $fields['name'] = preg_replace( '/\s+/', '', $fields['name'] );
        }
        return $fields;
    }

    //action when a post has been created
    public function dt_post_created( $post_type, $post_id, $initial_fields ){
        if ( $post_type === $this->post_type ){
            $test  = '';
        }
    }

    // scripts
    public function scripts(){
        // @todo add check  for 'Can view social conversations' capability
        // if ( ) ){
            // @todo add enqueue scripts
            wp_enqueue_script( 'conversation_scripts', trailingslashit( plugin_dir_url( __DIR__ ) ) . 'dist/conversation_scripts.js', [], filemtime( plugin_dir_path( __DIR__ ) . 'dist/conversation_scripts.js' ) );

            wp_register_style( 'conversation_css', trailingslashit( plugin_dir_url( __DIR__ ) ) . 'dist/styles.css', [], filemtime( trailingslashit( plugin_dir_path( __DIR__ ) ) . 'dist/styles.css' ) );
            wp_enqueue_style( 'conversation_css' );
        // }
    }

    public function dt_add_section( $post_type, $post ) {
        if ( $post_type === 'conversations' ){
            ?>
            <div class="cell small-12">
                <div class="bordered-box" id="conversations-tile">
                    <h3 class="section-header">
                        Conversations
                    </h3>
                    <div class="section-body">
                        <button class="button">Send Email</button>
                        <button class="button">Send SMS</button>
                        <button class="button">Send WhatsApp</button>
                    </div>

                </div>
            </div>

        <?php }
    }
}


