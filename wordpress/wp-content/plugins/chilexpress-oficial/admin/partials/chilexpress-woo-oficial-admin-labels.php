<!-- //NOSONAR -->
 <table class="">
	<tr>
		<td width="50%">
			<h4>Etiqueta</h4>
				<!-- //NOSONAR --><table class="form-table" role="presentation">
		<tbody>
			<tr>
					<!-- //NOSONAR --><th scope="row">Numero de OT</th>
				<td><input type="text"  disabled="disabled" value="<?php  echo $transportOrderNumbers[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Referencia</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $references[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Descripcion del producto</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $productDescriptions[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Descripción adicional</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $serviceDescription_[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Código de barras</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $barcodes[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Clasificación</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $classificationData_[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Compañia</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $companyName_[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Recibe</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $recipient_[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Dirección</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $address_[$i];  ?>" 
				class="regular-text"></td>
			</tr>
			<tr>
					<!-- //NOSONAR --><th scope="row">Fecha de impresión</th>
				<td><input type="text" disabled="disabled" value="<?php  echo $printedDate_[$i];  ?>" 
				class="regular-text"></td>
			</tr>
		</tbody>
	</table>
		</td>
		<td width="5%" valign="top">&nbsp;</td>
		<td width="45%" valign="top">
			<h4>Imagen de la etiqueta</h4>
			<?php echo '<img src="' . $src . '" />'; ?>
			<br />
			<br />
			<a  href="<?php echo $print_url; ?>" class="button button-primary" target="_blank">
				Imprimir
			</a>
		</td>
	</tr>

</table>
<hr />