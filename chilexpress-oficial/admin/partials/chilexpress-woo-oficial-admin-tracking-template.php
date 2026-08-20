<?php ?>
 			<script type="text/template" id="tmpl-wc-modal-track-order">
			<div class="wc-backbone-modal wc-track-order">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
							<h1>Orden de transporte N º {{ data.data.transportOrderData.transportOrderNumber }}</h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text">Cerrar ventana modal</span>
							</button>
						</header>
						<article>
								<div>
									<div style="width:50%; float:left;">
										<ul>
											<li><strong>Producto:</strong> {{ data.data.transportOrderData.product }}</li>
											<li><strong>Servicio</strong> {{ data.data.transportOrderData.service }}</li>
											<li><strong>Estado</strong> {{ data.data.transportOrderData.status }}</li>
										</ul>
									</div>
									<div style="width:50%; float:right;">
										<ul>
											<li><strong>Dimensones</strong> {{ data.data.transportOrderData.dimensions }} m</li>
											<li><strong>Peso</strong> {{ data.data.transportOrderData.weight }} kg</li>
										</ul>
									</div>
									<div style="clear:both"></div>
									<h2>Datos de entrega</h2>
									<div>
										<ul>
											<li><strong>Rut Receptor:</strong> {{ data.data.deliveryData.receptorRut }}</li>
											<li><strong>Nombre Receptor</strong> {{ data.data.deliveryData.receptorName }}</li>
											<li><strong>Fecha de Entrega</strong> {{ data.data.deliveryData.deliveryDate }}</li>
											<li><strong>Hora Entrega</strong> {{ data.data.deliveryData.deliveryHour }}</li>
										</ul>
									</div>
									<h2>Eventos</h2>
									<div>
										<table id="wc-chilexpress-events" class="widefat striped">
											<thead>
												<tr>
													<th scope="column">Fecha</th>
													<th scope="column">Hora</th>
													<th scope="column">Descripción</th>
													<th scope="column">&nbsp</th>
												</tr>
											</thead>
											<tbody>
												<tr>
													<td colspan="4" style="text-align:center;">
														Cargando...
													</td>
												<tr>
											</tbody>
										</table>
									</div>						    
								</div>
							</article>
						<footer>
							<div class="inner">
								

								
							</div>
						</footer>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
<?php  ?>