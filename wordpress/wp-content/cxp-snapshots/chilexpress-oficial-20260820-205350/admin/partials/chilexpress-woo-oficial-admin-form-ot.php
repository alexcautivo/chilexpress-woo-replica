<?php
	$shipping_key = 'shipping';
	$billing_key = 'billing';
	$email_key = 'email';
	$phone_key = 'phone';
	$show_btn_got = true;
	$text_to_user = 'Campo Requerido';
	$text_color = 'background-color:red';
?>
	<form action="" method="post">
		<h2>Generar OT</h2>
		<h3 id="direccion-destino">Dirección de Destino</h3>
		<input type="hidden" name="subaction" value="generar"/>
		<input type="hidden" name="referer" value="<?php echo esc_attr($_SERVER["HTTP_REFERER"]); ?>"/>
		<table class="form-table" aria-describedby="direccion-destino" >
			<tbody>
				<tr>
					<th scope="col">Código de comuna de destino</th>
					<td><input type="text" name="generar_ot[codigo_comuna_destino]" disabled="disabled"
					 value="<?php echo $comuna_code; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<td>Nombre de calle</td>
					<td><input type="text" name="generar_ot[calle_destino]" disabled="disabled"
					 value="<?php echo $order_data[$shipping_key]["address_1"]; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<td>Número de calle</td>
					<td><input type="text" name="generar_ot[numero_calle_destino]" disabled="disabled"
					 value="<?php echo $order_data[$shipping_key]["address_2"]; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<td>Complemento</td>
					<td><input type="text" name="generar_ot[complemento_destino]" disabled="disabled"
					 value="<?php echo isset($complemento)?esc_attr($complemento):''; ?>" class="regular-text"></td>
				</tr>
			</tbody>
		</table>
		<h3 id="direccion-devolucion">Dirección de devolución</h3>
		<table class="form-table" aria-describedby="direccion-devolucion">
			<tbody>
				<tr>
					<th scope="col">Código de comuna de devolución</th>
					<td><input type="text" name="generar_ot[codigo_comuna_devolucion]" 
					disabled="disabled" 
					value="<?php if(isset($options['comuna_devolucion'])){
						 echo $options['comuna_devolucion']; 
						} ?>"
					 class="regular-text"></td>
				</tr>
				<tr>
					<td>Nombre de calle</td>
					<td><input type="text" name="generar_ot[calle_devolucion]" disabled="disabled"
					 value="<?php if(isset($options['calle_devolucion'])){
						 echo $options['calle_devolucion']; 
					} ?>" class="regular-text"></td>
				</tr>
				<tr>
					<td>Número de calle</td>
					<td><input type="text" name="generar_ot[numero_calle_devolucion]" 
					disabled="disabled" value="<?php if(isset($options['numero_calle_devolucion'])){ 
						echo $options['numero_calle_devolucion']; 
					} ?>" class="regular-text"></td>
				</tr>
				<tr>
					<td>Complemento</td>
					<td><input type="text" name="generar_ot[complemento_devolucion]" 
					disabled="disabled" value="<?php if(isset($options['complemento_devolucion'])){
						echo $options['complemento_devolucion']; 
					} ?>" class="regular-text"></td>
				</tr>
			</tbody>
		</table>
		<h3 id="datos-remitente">Datos del remitente</h3>
		<table class="form-table" aria-describedby="datos-remitente">
			<tbody>
				<tr>
					<th scope="col">Nombre</th>
					<td><input type="text" name="generar_ot[nombre_remitente]" 
					disabled="disabled" value="<?php if(isset($options['nombre_remitente'])){
						echo $options['nombre_remitente']; 
					} ?>" class="regular-text"></td>
				</tr>
				<tr>
					<td>Teléfono</td>
					<td><input type="text" name="generar_ot[telefono_remitente]" 
					disabled="disabled" value="<?php if(isset($options['telefono_remitente'])){
						echo $options['telefono_remitente']; 
					} ?>" class="regular-text"></td>
				</tr>
				<tr>
					<td>E-mail</td>
					<td><input type="text" name="generar_ot[email_remitente]" 
					disabled="disabled" value="<?php if(isset($options['email_remitente'])){
						echo $options['email_remitente'];
					} ?>" class="regular-text"></td>
				</tr>
			</tbody>
		</table>
		<h3 id="datos-destinatario">Datos del destinatario</h3>
		<table class="form-table" aria-describedby="datos-destinatario">
			<tbody>
				<tr>
					<th scope="col">Nombre</th>
					<td>
						<?php $name_full = 
							$order_data[$shipping_key]["first_name"]." ".$order_data[$shipping_key]["last_name"];?>
						<input disabled class="regular-text" type="text" 
						name="generar_ot[nombre_destinatario]"
						value="<?php echo $name_full !== ' ' ? $name_full : $text_to_user; ?>"
						style="<?php echo $name_full !== ' ' ? "" : $text_color; ?>" > 
					   	<?php $show_btn_got = ($name_full !== ' ' && $show_btn_got) ;?>
					</td>

				</tr>
				<tr>
					<td>Teléfono</td>
					<td>
						<input disabled class="regular-text" type="text"
						 name="generar_ot[telefono_destinatario]" 
						 value="<?php 
						 	echo $order_data[$billing_key][$phone_key] !== '' ?
						 	$order_data[$billing_key][$phone_key] :
							$text_to_user;
						 ?>" 
						 style="<?php echo $order_data[$billing_key][$phone_key] !== '' ? "" : $text_color; ?>"> 
					   	<?php $show_btn_got = ($order_data[$billing_key][$phone_key] !== '' && $show_btn_got);?>
					</td>

				</tr>
				<tr>
					<td>E-mail</td>
					<td>
						<input disabled class="regular-text" type="text" 
						name="generar_ot[email_destinatario]" 
						value="<?php 
							echo $order_data[$billing_key][$email_key]!== '' ?
								 $order_data[$billing_key][$email_key] :
								 $text_to_user;
						?>" style="<?php echo $order_data[$billing_key][$email_key] !== '' ? "" : $text_color; ?>"> 
					    <?php $show_btn_got = ($order_data[$billing_key][$email_key] !== '' && $show_btn_got); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<h3 id="armado-bultos">Armado de bultos</h3>

		<table class="widefat striped" style="float:center; width: 98%;" aria-describedby="armado-bultos">
			<thead>
				<tr>
					<th scope="col">Id</th>
					<th scope="col">Nombre</th>
					<th scope="col">Cantidad</th>
					<th scope="col">Dimensiones</th>
					<th scope="col">Peso Total</th>
					<th scope="col">Bulto</th>
				</tr>
			</thead>
			<tbody>
				<?php
					$paquetes = count($order->get_items());

					foreach ($order->get_items() as $item_key => $item ):

					    ## Using WC_Order_Item methods ##

					    // Item ID is directly accessible from the $item_key in the foreach loop or
					    $item_id = $item->get_id();

					    ## Using WC_Order_Item_Product methods ##

					    $product      = $item->get_product(); // Get the WC_Product object

					    $product_id   = $item->get_product_id(); // the Product id

					    $item_type    = $item->get_type(); // Type of the order item ("line_item")

					    $item_name    = $item->get_name(); // Name of the product
					    $quantity     = $item->get_quantity();  
					   

					    // Get data from The WC_product object using methods (examples)
					    $product        = $item->get_product(); // Get the WC_Product object
					    $stock_quantity = $product->get_stock_quantity();
				    ?>
				    <tr>
					<td>
						<span><?php echo $product_id; ?></span>
					</td>
					<td>
						<span><?php echo $item_name; ?></span>
					</td>
					<td>
						<span><?php echo $quantity; ?></span>
					</td>
					<td>
						<?php
							$dimensiones = $product->get_dimensions(false);
							$options = get_option( 'chilexpress_woo_oficial_general' );

							$alto = ( !empty($dimensiones['height']) || $dimensiones['height'] != 0 ) ?
										$dimensiones['height'] :
										$options['alto_producto_defecto'];
							$ancho = ( !empty($dimensiones['width']) || $dimensiones['width'] != 0 ) ?
										$dimensiones['width'] :
										$options['ancho_producto_defecto'];
							$largo = ( !empty($dimensiones['length']) || $dimensiones['length'] != 0 ) ?
										$dimensiones['length'] :
										$options['largo_producto_defecto'];

							$array_dimensiones = array(
						    	'length' => $largo,
						      	'width'  => $ancho,
						      	'height' => $alto,
						    );
						    
							echo wc_format_dimensions($array_dimensiones);  
						?> 
					</td>
					<td>
						<?php  echo $product->get_weight() ? wc_format_weight($product->get_weight()*$quantity) :
							 $options['peso_producto_defecto'].'kg'; ?>
					</td>
					<td>
						<select name="paquetes[<?php echo $product_id; ?>]">
							<?php for($i=1;$i<=$paquetes;$i++){ ?>
							<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>
				    <?php
				endforeach;

				?>
				
			</tbody>
		</table>

		<table style="float:right; width: 500px;" aria-describedby="Tabla de Bton">
			<tbody>
				<tr>
					<th scope="col"></th>
					<th scope="col"></th>
					<th scope="col"></th>
    				<th scope="col">Generar Orden de Transporte</th>
				</tr>
				<tr>
					<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
					<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
					<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
					<td>
						<?php 
							$continuar_orden = $continuar_orden && $show_btn_got;
							if( $continuar_orden ) {  
								submit_button("Generar OT"); 
							} 
						?>
					</td>
				</tr>
				<tr> 
				  <td colspan=4 > 
					<?php $continuar_orden = $continuar_orden && $show_btn_got;
						if( !$continuar_orden ) { ?>
						  <h3 style="color:red; font-weight: bold;"> Edita los elementos destacados en rojo
							 y vuelve a intentarlo.</h3> 
					    <?php } ?>
				  </td>
				</tr>
			</tbody>
		</table>
	</form>
