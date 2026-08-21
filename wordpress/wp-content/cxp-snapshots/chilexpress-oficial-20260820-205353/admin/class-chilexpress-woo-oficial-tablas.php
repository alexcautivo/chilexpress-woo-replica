<?php 
	class Tabla_Pedidos_Chilexpress extends WP_List_Table
	{
		
		const TRANSPORT_ORDER_NUMBERS = 'transportOrderNumbers';
		const CERTIFICATE_NUMBER = 'certificateNumber';
	    
	    /**
	     * Prepare the items for the table to process
	     *
	     * @return Void
	     */
	    public function prepare_items()
	    {
	        $columns = $this->get_columns();
	        $hidden = $this->get_hidden_columns();
	        $sortable = $this->get_sortable_columns();

	        $data = $this->table_data();
	        usort( $data, array( &$this, 'sort_data' ) );

	        $i = 0;
		    foreach ($data AS $key) {
		        if (isset($key['fecha'])) {
		            $data[$i]['fecha'] = date("d M, Y", strtotime($key['fecha']));
		        }
		        $i++;
		    }

	        $perPage = 10;
	        $currentPage = $this->get_pagenum();
	        $totalItems = count($data);

	        $this->set_pagination_args( array(
	            'total_items' => $totalItems,
	            'per_page'    => $perPage
	        ) );

	        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

	        $this->_column_headers = array($columns, $hidden, $sortable);
	        $this->items = $data;
	    }

	    /**
	     * Override the parent columns method. Defines the columns to use in your listing table
	     *
	     * @return Array
	     */
	    public function get_columns()
	    {
	        $columns = array(
	        	//'cb'           => '<input type="checkbox" />',
	            'pedido_id'    => 'Nro Pedido',
	            'destinatario' => 'Destinatario',
	            'fecha'        => 'Fecha Pedido',
	            'numero_ot'    => 'Orden Transporte',
	            'numero_ce'    => 'N° Certificado',
	            'servicio'     => 'Servicio',
	            'costo'        => 'Costo del Envío',
	            'etiqueta'     => 'Etiqueta'
	        );

	        return $columns;
	    }

	    /**
	     * Define which columns are hidden
	     *
	     * @return Array
	     */
	    public function get_hidden_columns()
	    {
	        return array("ID");
	    }

	    /**
	     * Define the sortable columns
	     *
	     * @return Array
	     */
	    public function get_sortable_columns()
	    {
	    	$columns = array(
	    		'pedido_id' => array('pedido_id', true),
	    		'fecha'     => array('fecha', false),
	    	);
	        return $columns;
	    }

	    /**
	     * Get the table data
	     *
	     * @return Array
	     */
	    private function table_data()
	    {
	        global $wpdb;

	        $query = "
	        	SELECT
	        		post.ID AS orden_id,
				  	DATE_FORMAT(post.date_created_gmt, '%d-%m-%Y') AS fecha,
				  	metaFirstName.meta_value AS destinatario,
				  	metaOTNumber.meta_value AS numero_ot,
				  	metaCNumber.meta_value AS numero_certificado,
                    metaCostoEnvio.meta_value AS costo_envio
				FROM {$wpdb->prefix}wc_orders AS post
				    LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_shipping_address_index'
				    ) AS metaFirstName
				ON post.ID = metaFirstName.order_id
				    LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_shipping_last_name'
				    ) AS metaLastName
				ON post.ID = metaLastName.order_id
					LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = 'transportOrderNumbers'
				    ) AS metaOTNumber
				ON post.ID = metaOTNumber.order_id
					LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = 'certificateNumber'
				    ) AS metaCNumber
				ON post.ID = metaCNumber.order_id
                LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_shipping_total'
				    ) AS metaCostoEnvio
				ON post.ID = metaCostoEnvio.order_id

				WHERE metaOTNumber.meta_value != ''

	        "; // AND post.post_status = 'wc-completed'

	        if( isset($_GET['s']) )
			{
				$str = $_GET['s'];
				$query = $query." 
					AND (
						post.ID LIKE '%".$wpdb->esc_like($str)."%' OR 
						DATE_FORMAT(post.post_date, '%d-%m-%Y') LIKE '%".$wpdb->esc_like($str)."%' OR 
						metaOTNumber.meta_value LIKE '%".$wpdb->esc_like($str)."%' OR 
						metaCNumber.meta_value LIKE '%".$wpdb->esc_like($str)."%'
					)
				";
	    	}

	    	$query = $query." ORDER BY post.ID DESC";

			$results = $wpdb->get_results( $query , OBJECT );

			$data = array();

			foreach ($results as $key => $item) 
			{
				try {
					$order = new WC_Order( intval($item->orden_id) );
					$existe = strpos($order->get_shipping_method(), 'Chilexpress');
					if( ($key = $existe) !== false )
					{
						$actions = $this->order_status_actions_button( $order );
						$tracking = $this->get_tracking( $order->get_id() );
						$certificate = $this->get_certificate( $order->get_id() );
						$user_order = $order->get_user();
						$pedido_id = '<a href="'.admin_url('post.php?post='.$order->get_id().'&action=edit').'"> #'.
									$order->get_id().'</a>';

						$fecha = new DateTime($order->get_date_created());

						$data[] = array(
							//'ID'           => $order->get_id(),
							'pedido_id'    => $pedido_id,
							'destinatario' => $item->destinatario,
							'fecha'        => $fecha->format('d-m-Y'), //$fecha->format('d M, Y'),
							'numero_ot'    => $tracking,
							'numero_ce'    => $certificate,
							'servicio'     => '<small>'.$order->get_shipping_method().'</small>',
							'costo'        => wc_price( $order->get_shipping_total() ),
							'etiqueta'     => '<a class="button wc-action-button wc-action-button-'.$actions["action"].
											' '.$actions["action"].' " href="'.$actions["url"].
											'" aria-label="'.$actions["name"].'"></a>'
						);
					}
				} catch (Exception $e) {
					echo "Ha ocurrido un error con pedido Id[".$item->orden_id."]: Mensaje: ".$e->getMessage()."<BR>";
					// error_log("Error en mi_funcion_riesgosa(): " . $e->getMessage());
				}
			}

	        return $data;
	    }

	    /**
	     * Define what data to show on each column of the table
	     *
	     * @param  Array $item        Data
	     * @param  String $column_name - Current column name
	     *
	     * @return Mixed
	     */
	    public function column_default( $item, $column_name )
	    {
	        switch( $column_name ) {
	            case 'pedido_id':
	            case 'destinatario':
	            case 'fecha':
	            case 'numero_ot':
	            case 'numero_ce':
	            case 'servicio':
	            case 'costo':
	            case 'etiqueta':
	                return $item[ $column_name ];

	            default:
	                return print_r( $item, true ) ;
	        }
	    }

	    /**
	     * Allows you to sort the data by the variables set in the $_GET
	     *
	     * @return Mixed
	     */
	    private function sort_data( $a, $b )
	    {
	        // Set defaults
	        $orderby = 'pedido_id';
	        $order = 'desc';

	        // If orderby is set, use this as the sort column
	        if(!empty($_GET['orderby']))
	        {
	            $orderby = $_GET['orderby'];
	        }

	        // If order is set use this as the order
	        if(!empty($_GET['order']))
	        {
	            $order = $_GET['order'];
	        }

	        if($orderby == 'pedido_id')
	        {
	        	$_orderID1 = $this->get_order_id( $a[$orderby] );
	        	$_orderID2 = $this->get_order_id( $b[$orderby] );
	        	$result = ($_orderID1 > $_orderID2) ? +1 : -1;
	        }

	        if($orderby == 'fecha')
	        {
	        	$_fecha1 = strtotime( $a[$orderby] );
	        	$_fecha2 = strtotime( $b[$orderby] );
	        	$result = ($_fecha1 > $_fecha2) ? +1 : -1;
	        }

	        
	        if($order === 'asc')
	        {
	            return $result;
	        }

	        return -$result;
	    }


		public function get_order_id( $str ){
			$_str = strip_tags($str);
			$_str = trim($_str);
			$order_id = substr($_str, 1);
			return intval($order_id);
		}


		public function order_status_actions_button( $order ) {

		    $actions = array();
			$ot_status = $order->get_meta('ot_status');
			$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS );

			if($ot_status == 'created' && count($transportOrderNumbers) > 0) {
				$actions = array(
					'url'       =>  wp_nonce_url( 
								admin_url( 
									'admin.php?page=chilexpress_woo_oficial_generar_ot&action=imprimir_ot&order_id='.
									$order->get_id()
								).'&pedidos_cxp=1', 'generar-ot'),
					'name'      => 'Imprimir OT',
					'action'    => 'imprimir_ot',
				);
			}
		    return $actions;
		}


		public function get_tracking( $order_id ) {
			
			$tracking = '';
			$order = wc_get_order( $order_id );
			$transportOrderNumbers = $order->get_meta( self::TRANSPORT_ORDER_NUMBERS );
			if (is_array($transportOrderNumbers)) {
				$out = array();
				foreach($transportOrderNumbers as $transportOrderNumber) {
					$out[] = '<a href="javascript:;" class="tracking-link" data-pid="'.$order_id.'" data-ot="'.
							$transportOrderNumber.'">'.$transportOrderNumber.'</a>';
				}
				$tracking = implode(", ", $out);
			}

			return $tracking;

		}

		public function get_certificate( $order_id ) {
			
			$certificate = '';
			$order = wc_get_order( $order_id );
			$certificateNumber = $order->get_meta( self::CERTIFICATE_NUMBER );
			if ($certificateNumber) {
				$certificate = '<a href="#" class="numero-ce" data-order_id="'.$order_id.'" data-numero_ce="'.
						$certificateNumber.'">'.$certificateNumber.'</a>';
			}

			return $certificate;

		}

	}

	class Tabla_Productos_Chilexpress extends WP_List_Table
	{
	    /**
	     * Prepare the items for the table to process
	     *
	     * @return Void
	     */
	    public function prepare_items()
	    {
	        $columns = $this->get_columns();
	        $hidden = $this->get_hidden_columns();
	        $sortable = $this->get_sortable_columns();

	        $data = $this->table_data();
	        usort( $data, array( &$this, 'sort_data' ) );

	        $perPage = 10;
	        $currentPage = $this->get_pagenum();
	        $totalItems = count($data);

	        $this->set_pagination_args( array(
	            'total_items' => $totalItems,
	            'per_page'    => $perPage
	        ) );

	        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

	        $this->_column_headers = array($columns, $hidden, $sortable);
	        $this->items = $data;
	    }

	    /**
	     * Override the parent columns method. Defines the columns to use in your listing table
	     *
	     * @return Array
	     */
	    public function get_columns()
	    {
	        $columns = array(
	        	'img'    => '<span class="wc-image tips"></span>',
	            'nombre' => 'Producto',
	            'alto'   => 'Alto',
	            'largo'  => 'Largo',
	            'ancho'  => 'Ancho',
	            'peso'   => 'Peso',
	            'ampm'	 => 'Entrega en el mismo día',
	            'accion' => 'Acción'
	        );

	        return $columns;
	    }

	    /**
	     * Define which columns are hidden
	     *
	     * @return Array
	     */
	    public function get_hidden_columns()
	    {
	        return array('ID');
	    }

	    /**
	     * Define the sortable columns
	     *
	     * @return Array
	     */
	    public function get_sortable_columns()
	    {
	        return array('ID' => array('ID', false));
	    }

	    /**
	     * Get the table data
	     *
	     * @return Array
	     */
	    private function table_data()
	    {
	        
	        global $wpdb;

	        $query = "
	        	SELECT
	        		post.ID AS producto_id,
				  	post.post_title AS producto,
				  	metaHeight.meta_value AS alto,
				  	metaLength.meta_value AS largo,
				  	metaWidth.meta_value AS ancho,
				  	metaWeight.meta_value AS peso,
				  	metaAmpm.meta_value AS ampm
				FROM {$wpdb->prefix}posts AS post
				    LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '_height'
				    ) AS metaHeight
				ON post.ID = metaHeight.post_id
				    LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '_length'
				    ) AS metaLength
				ON post.ID = metaLength.post_id
					LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '_width'
				    ) AS metaWidth
				ON post.ID = metaWidth.post_id
					LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '_weight'
				    ) AS metaWeight
				ON post.ID = metaWeight.post_id
				LEFT JOIN (
				      SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '_ampm'
				    ) AS metaAmpm
				ON post.ID = metaAmpm.post_id

				WHERE post.post_type = 'product'
				AND post.post_status = 'publish'
	        ";

	        if( isset($_POST['s']) )
			{
				$str = $_POST['s'];
				$query = $query." AND post.post_title LIKE '%".$wpdb->esc_like($str)."%'";
	    	}

			$results = $wpdb->get_results( $query , OBJECT );

			$data = array();
			$options = get_option( 'chilexpress_woo_oficial_general' );

			foreach ($results as $key => $item)
			{
				$product = new WC_Product( $item->producto_id );

				$btn_accion = '<button type="button" style="background:green; color:white;">Ok</button>';
				if( 
					$product->get_height() === '' ||
					$product->get_length() === '' ||
					$product->get_width() === '' ||
					$product->get_weight()
				) {
					$btn_accion = '<button type="button" class="btn-asignar-dimension" data-product_id="'.
							$product->get_id().'" >Asignar</button>';
				}

				$alto = ( $product->get_height() != '' ) ? 
							floatval( $product->get_height('edit') ) :
							floatval( $options['alto_producto_defecto'] );
				$largo = ( $product->get_length() != '' ) ?
							floatval( $product->get_length('edit') ) :
							floatval( $options['largo_producto_defecto'] );
				$ancho = ( $product->get_width() != '' ) ?
							floatval( $product->get_width('edit') ) :
							floatval( $options['ancho_producto_defecto'] );
				$peso = ( $product->get_weight() != '' ) ?
							floatval( $product->get_weight('edit') ) :
							floatval( $options['peso_producto_defecto'] );

	
				$_ampm_meta = get_post_meta( $product->get_id(), '_ampm', true );
				$ampm = ( $_ampm_meta != '' ) ? $_ampm_meta : '';
				$value_param = "' value='";
				$data[] = array(
					'ID'     => $product->get_id(),
				    'img'    => wp_get_attachment_image( $product->get_image_id(), array(50, 50) ),
				    'nombre' => "<a href='".admin_url('post.php?post='.$product->get_id().'&action=edit')."'>".
							$product->get_name()."</a>",
				    'alto'   => "<input type='number' style='width: 80px;' step='any' min='1' id='input_alto_".
							$product->get_id().$value_param.$alto."' />",
				    'largo'  => "<input type='number' style='width: 80px;' step='any' min='1' id='input_largo_".
							$product->get_id().$value_param.$largo."' />",
				    'ancho'  => "<input type='number' style='width: 80px;' step='any' min='1' id='input_ancho_".
							$product->get_id().$value_param.$ancho."' />",
				    'peso'   => "<input type='number' style='width: 80px;' step='any' min='0' id='input_peso_".
							$product->get_id().$value_param.$peso."' />",
				    'ampm'   => "<input type='checkbox' id='input_ampm_".$product->get_id()."' value='checked' ".
							$ampm." />",
				    'accion' => $btn_accion
				);	
			}

	        return $data;
	    }

	    /**
	     * Define what data to show on each column of the table
	     *
	     * @param  Array $item        Data
	     * @param  String $column_name - Current column name
	     *
	     * @return Mixed
	     */
	    public function column_default( $item, $column_name )
	    {
	        switch( $column_name ) {
	        	case 'ID':
	            case 'img':
	            case 'nombre':
	            case 'alto':
	            case 'largo':
	            case 'ancho':
	            case 'peso':
	            case 'ampm':
	            case 'accion':
	                return $item[ $column_name ];

	            default:
	                return print_r( $item, true ) ;
	        }
	    }

	    /**
	     * Allows you to sort the data by the variables set in the $_GET
	     *
	     * @return Mixed
	     */
	    private function sort_data( $a, $b )
	    {
	        // Set defaults
	        $orderby = 'ID';
	        $order = 'desc';

	        // If orderby is set, use this as the sort column
	        if(!empty($_GET['orderby']))
	        {
	            $orderby = $_GET['orderby'];
	        }

	        // If order is set use this as the order
	        if(!empty($_GET['order']))
	        {
	            $order = $_GET['order'];
	        }


	        $result = strcmp( $a[$orderby], $b[$orderby] );

	        if($order === 'asc')
	        {
	            return $result;
	        }

	        return -$result;
	    }

	}
?>
