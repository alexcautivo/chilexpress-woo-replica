(function () {
	if (!window.wc || !window.wc.wcBlocksRegistry || !window.wp || !window.wp.element) {
		return;
	}

	var settings = window.wc.wcSettings.getSetting("cxp_test_card_data", null);
	if (!settings) {
		var methods = window.wc.wcSettings.getSetting("paymentMethodData", {});
		settings = (methods && methods.cxp_test_card) || {};
	}
	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var decode = window.wp.htmlEntities.decodeEntities;

	function Field(props) {
		return el(
			"label",
			{ style: { display: "block", margin: "8px 0", fontSize: "14px" } },
			props.label,
			el("input", {
				type: "text",
				value: props.value,
				onChange: function (event) {
					props.onChange(event.target.value);
				},
				autoComplete: props.autoComplete || "off",
				inputMode: props.inputMode || "numeric",
				style: {
					display: "block",
					width: "100%",
					marginTop: "4px",
					padding: "10px 12px",
					border: "1px solid #111",
					borderRadius: "8px",
					font: "inherit",
				},
			})
		);
	}

	function Content(props) {
		var numberState = useState(settings.testCard || "4242424242424242");
		var expState = useState(settings.testExp || "12/34");
		var cvcState = useState(settings.testCvc || "123");
		var number = numberState[0];
		var setNumber = numberState[1];
		var exp = expState[0];
		var setExp = expState[1];
		var cvc = cvcState[0];
		var setCvc = cvcState[1];

		useEffect(
			function () {
				var unsubscribe = props.eventRegistration.onPaymentSetup(function () {
					var digits = String(number).replace(/\D/g, "");
					if (digits !== "4242424242424242" && digits !== "4111111111111111") {
						return {
							type: props.emitResponse.responseTypes.ERROR,
							message: "Tarjeta rechazada. Usa 4242 4242 4242 4242 (debug).",
						};
					}
					return {
						type: props.emitResponse.responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								cxp_card_number: digits,
								cxp_card_exp: exp,
								cxp_card_cvc: cvc,
							},
						},
					};
				});
				return unsubscribe;
			},
			[number, exp, cvc, props.eventRegistration, props.emitResponse]
		);

		return el(
			"div",
			{ className: "cxp-test-card" },
			el(
				"p",
				{ style: { margin: "0 0 10px" } },
				decode(settings.description || "")
			),
			el(Field, {
				label: "Número de tarjeta",
				value: number,
				onChange: setNumber,
				autoComplete: "cc-number",
			}),
			el(Field, {
				label: "Vencimiento (MM/AA)",
				value: exp,
				onChange: setExp,
				autoComplete: "cc-exp",
			}),
			el(Field, {
				label: "CVC",
				value: cvc,
				onChange: setCvc,
				autoComplete: "cc-csc",
			})
		);
	}

	window.wc.wcBlocksRegistry.registerPaymentMethod({
		name: "cxp_test_card",
		label: el("span", null, decode(settings.title || "Tarjeta de prueba (debug)")),
		ariaLabel: decode(settings.title || "Tarjeta de prueba (debug)"),
		content: el(Content, null),
		edit: el(Content, null),
		canMakePayment: function () {
			return true;
		},
		supports: {
			features: settings.supports || ["products"],
		},
	});
})();
