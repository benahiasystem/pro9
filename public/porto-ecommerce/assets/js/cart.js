// ───────────────────────────────────────────────────────────────────────────
// Modal de confirmación al agregar al carrito
// ───────────────────────────────────────────────────────────────────────────
var _cartConfirmState = {
	itemId: null,
	stock: null,
};

function cartNormalizeItem(raw) {
	let item = typeof raw === 'string' ? JSON.parse(raw) : Object.assign({}, raw);

	if (!item.currency_type_symbol) {
		item.currency_type_symbol = (item.currency_type && item.currency_type.symbol)
			? item.currency_type.symbol
			: (item.currency_type && item.currency_type['symbol'])
				? item.currency_type['symbol']
				: 'Bs.';
	}

	if (item.original_price == null && item.old_price != null) {
		item.original_price = item.old_price;
	}

	if (item.has_discount == null && item.hasDiscount != null) {
		item.has_discount = item.hasDiscount;
	}

	return item;
}

function cartParseProductFromButton(btn) {
	if (!btn) {
		return null;
	}

	let raw = btn.getAttribute('data-product');
	if (!raw) {
		return null;
	}

	try {
		return cartNormalizeItem(JSON.parse(raw));
	} catch (e) {
		return null;
	}
}

// Structured server prices seed storage; rounded DOM labels never become prices.
function cartEnrichFromProductCard(btn, item) { return item; }

function cartAmounts() {
    if (!window.SabStorefrontAmounts) throw new Error('No se pudo cargar el carrito. Recarga la página.');
    return window.SabStorefrontAmounts;
}

function cartGetProductStock(item) { return cartAmounts().stock(item); }

function cartClampQuantity(value, stock, allowZero) {
    let amount = cartAmounts().amount(cartAmounts().input(String(value)));
    if (amount.compare('0') < 0 || (!allowZero && amount.compare('0') === 0)) throw new Error('La cantidad debe ser positiva.');
    if (stock !== null && amount.compare(stock) > 0) amount = cartAmounts().amount(stock);
    return amount.decimal();
}

function cartGetPricing(item) {
    const symbol = item.currency_type_symbol || 'Bs.';
    if (item.sale_unit_price == null) {
        if (window.__storefront_show_prices !== false && window.__storefront_show_prices !== 0) throw new Error('El precio no está disponible. Actualiza el catálogo.');
        return {symbol, currentPrice:null, originalPrice:null, basePrice:null, hasDiscount:false, discountPercent:null};
    }
    const current = cartAmounts().line(Object.assign({}, item, {cantidad:'1'}), {convert:false}).sourceUnitPrice;
    let original = cartAmounts().amount(item.compare_at_price ?? item.original_price ?? item.old_price ?? current.fraction());
    const preserved = item.campaign_pricing_snapshot?.values;
    const anchor = preserved?.compare_at_price;
    if (anchor && cartAmounts().amount(anchor).decimal() === original.decimal()) original = cartAmounts().amount(anchor);
    const hasDiscount = original.compare(current.fraction()) > 0;
    const supplied = cartAmounts().amount(item.discount_percent ?? '0');
    const percent = hasDiscount && original.compare('0') > 0
        ? (supplied.compare('0') > 0 ? supplied : original.minus(current.fraction()).dividedBy(original).times('100')).final(0) : null;
    return {symbol, currentPrice:current.decimal(), currentFraction:current.fraction(),
        basePrice:cartAmounts().amount(item.original_price ?? current.fraction()).decimal(),
        originalPrice:hasDiscount ? original.decimal() : current.decimal(),
        originalFraction:hasDiscount ? original.fraction() : current.fraction(), hasDiscount, discountPercent:percent};
}

function cartPrepareItemForStorage(item) {
    const pricing = cartGetPricing(item);
    const prepared = Object.assign({}, item, {sale_unit_price:pricing.currentPrice,
        original_price:pricing.basePrice, compare_at_price:pricing.hasDiscount ? pricing.originalPrice : null,
        has_discount:pricing.hasDiscount, discount_percent:pricing.discountPercent,
        quantity:cartAmounts().quantity(item.quantity ?? item.cantidad ?? '1')});
    if (!prepared.image_small && prepared.image) prepared.image_small = prepared.image;
    return prepared;
}

function cartReadCart() {
	try {
		const records = JSON.parse(localStorage.getItem('products_cart') || '[]');
        return Array.isArray(records) ? records : [];
	} catch (e) {
		return [];
	}
}

function cartWriteCart(array) {
	localStorage.setItem('products_cart', JSON.stringify(array));
}

function cartRefreshHeader() {
	if (typeof productsCartDropDown === 'function') {
		productsCartDropDown();
	}
	if (typeof calculateTotalCart === 'function') {
		calculateTotalCart();
	}
	window.dispatchEvent(new Event('productAddedToCart'));
}

function cartConfirmImagePath(item) {
	const imageName = item.image_medium || item.image_small || item.image;
	if (imageName && imageName !== 'imagen-no-disponible.jpg') {
		return '/storage/uploads/items/' + imageName;
	}
	return '/logo/imagen-no-disponible.jpg';
}

function cartConfirmUpdateQtyButtons() {
    const input = document.getElementById('cart-confirm-qty-input');
    const minusBtn = document.getElementById('cart-confirm-qty-minus');
    const plusBtn = document.getElementById('cart-confirm-qty-plus');
    if (!input || !minusBtn || !plusBtn) return;
    try {
        const quantity = cartClampQuantity(input.value, _cartConfirmState.stock, true);
        input.setCustomValidity('');
        minusBtn.disabled = cartAmounts().amount(quantity).compare('0') <= 0;
        plusBtn.disabled = _cartConfirmState.stock !== null && cartAmounts().amount(quantity).compare(_cartConfirmState.stock) >= 0;
    } catch (error) {input.setCustomValidity(error.message);}
}

function cartConfirmSyncQuantityToCart() {
    const input = document.getElementById('cart-confirm-qty-input');
    if (!input || _cartConfirmState.itemId == null) return;
    try {
        const quantity = cartClampQuantity(input.value, _cartConfirmState.stock, true);
        const records = cartReadCart();
        const index = records.findIndex(row => String(row.id) === String(_cartConfirmState.itemId));
        if (cartAmounts().amount(quantity).compare('0') === 0) {
            if (index !== -1) records.splice(index, 1);
        } else if (index !== -1) records[index].quantity = records[index].cantidad = quantity;
        else return;
        cartWriteCart(records);
        cartRefreshHeader();
        input.value = quantity;
        input.setCustomValidity('');
        cartConfirmUpdateQtyButtons();
    } catch (error) {input.setCustomValidity(error.message);input.reportValidity();}
}

function cartConfirmRenderPrices(item) {
	const pricing = cartGetPricing(item);
	const currentEl = document.getElementById('cart-confirm-price-current');
	const oldEl = document.getElementById('cart-confirm-price-old');

	if (!currentEl || !oldEl) {
		return;
	}

	if (pricing.currentPrice === null) { currentEl.innerHTML = ''; oldEl.innerHTML = ''; oldEl.style.display = 'none'; return; }

	if (pricing.hasDiscount) {
		currentEl.innerHTML =
			'<span class="product-price cart-confirm-current-price">' +
				pricing.symbol + ' ' + cartAmounts().money(pricing.currentFraction) +
			'</span>' +
			(pricing.discountPercent != null && cartAmounts().amount(pricing.discountPercent).compare('0') > 0
				? '<span class="cart-confirm-discount-badge">-' + pricing.discountPercent + '%</span>'
				: '');

		oldEl.innerHTML =
			'<span class="old-price cart-confirm-old-price">' +
				pricing.symbol + ' ' + cartAmounts().money(pricing.originalFraction) +
			'</span>';
		oldEl.style.display = 'block';
	} else {
		currentEl.innerHTML =
			'<span class="product-price cart-confirm-current-price">' +
				pricing.symbol + ' ' + cartAmounts().money(pricing.currentFraction) +
			'</span>';
		oldEl.innerHTML = '';
		oldEl.style.display = 'none';
	}
}

function cartConfirmDisplayItem(prepared, found) {
	const displayItem = Object.assign({}, prepared, found || {});
	displayItem.sale_unit_price = prepared.sale_unit_price;
	displayItem.original_price = prepared.original_price;
	displayItem.has_discount = prepared.has_discount;
	displayItem.discount_percent = prepared.discount_percent;
	if (found) {
		displayItem.quantity = found.quantity;
	}
	return displayItem;
}

function cartConfirmBindEvents() {
    const minusBtn = document.getElementById('cart-confirm-qty-minus');
    const plusBtn = document.getElementById('cart-confirm-qty-plus');
    const input = document.getElementById('cart-confirm-qty-input');
    const modal = document.getElementById('moda-succes-add-product');
    if (!minusBtn || minusBtn.dataset.bound === '1') return;
    minusBtn.dataset.bound = '1';
    const step = delta => {
        try {
            let quantity = cartAmounts().amount(cartClampQuantity(input.value, _cartConfirmState.stock, true)).plus(delta);
            if (quantity.compare('0') < 0) quantity = cartAmounts().amount('0');
            input.value = cartClampQuantity(quantity.decimal(), _cartConfirmState.stock, true);
            cartConfirmSyncQuantityToCart();
        } catch (error) {input.setCustomValidity(error.message);input.reportValidity();}
    };
    minusBtn.addEventListener('click', () => step('-1'));
    plusBtn.addEventListener('click', () => step('1'));
    input.addEventListener('change', cartConfirmSyncQuantityToCart);
    if (modal) {
        jQuery(modal).off('hidden.bs.modal.cartConfirm').on('hidden.bs.modal.cartConfirm', function () {
            cartConfirmSyncQuantityToCart(); _cartConfirmState.itemId = null;
        });
        jQuery(modal).off('shown.bs.modal.cartConfirm').on('shown.bs.modal.cartConfirm', cartConfirmUpdateQtyButtons);
    }
}

function showCartConfirmModal(item, mode) {
	item = cartNormalizeItem(item);
	mode = mode || 'added';

	const prepared = cartPrepareItemForStorage(item);
	const array = cartReadCart();
	const found = array.find(function (x) { return String(x.id) === String(prepared.id); });
	const displayItem = cartConfirmDisplayItem(prepared, found);
	const quantity = cartAmounts().quantity(found ? found.quantity : prepared.quantity);

	_cartConfirmState.itemId = prepared.id;
	_cartConfirmState.stock = cartGetProductStock(displayItem);

	const alertEl = document.getElementById('cart-confirm-alert');
	const alertText = document.getElementById('cart-confirm-alert-text');
	const titleEl = document.getElementById('cart-confirm-title');
	const imageEl = document.getElementById('product_added_image');
	const input = document.getElementById('cart-confirm-qty-input');

	if (alertEl && alertText) {
		if (mode === 'exists') {
			alertEl.className = 'cart-confirm-alert alert alert-warning';
			alertText.textContent = 'Tu producto ya está agregado al carrito.';
		} else {
			alertEl.className = 'cart-confirm-alert alert alert-success';
			alertText.textContent = 'Tu producto se agregó al carrito';
		}
	}

	if (titleEl) {
		titleEl.textContent = prepared.description || prepared.name || '';
	}

	if (imageEl) {
		imageEl.innerHTML =
			'<img src="' + cartConfirmImagePath(prepared) + '" class="img" alt="' +
			(prepared.description || '') + '">';
	}

	cartConfirmRenderPrices(displayItem);

	if (input) {
		input.value = quantity;
	}

	cartConfirmBindEvents();
	cartConfirmUpdateQtyButtons();

	jQuery('#moda-succes-add-product').modal('show');
}

function cartAddOrUpdateItem(item, options) {
    options = options || {};
    const structured = options.triggerEl ? cartParseProductFromButton(options.triggerEl) : null;
    const prepared = cartPrepareItemForStorage(cartNormalizeItem(structured || item));
    const records = cartReadCart();
    const found = records.find(row => String(row.id) === String(prepared.id));
    const requested = cartAmounts().quantity(options.quantity ?? prepared.quantity);
    const sum = found && !options.replaceQuantity
        ? cartAmounts().amount(cartAmounts().quantity(found.quantity)).plus(requested).decimal() : requested;
    const stock = cartGetProductStock(prepared);
    const quantity = cartClampQuantity(sum, stock, false);
    if (cartAmounts().amount(quantity).compare('0') <= 0) throw new Error('El producto está agotado.');
    const target = found || prepared;
    Object.assign(target, prepared, {quantity, cantidad:quantity});
    if (!found) records.push(target);
    cartWriteCart(records);
    cartRefreshHeader();
    showCartConfirmModal(target, options.mode || (found ? 'exists' : 'added'));
    return target;
}

function cart_add(data) {
	try {
		let item = cartNormalizeItem(data);
		cartAddOrUpdateItem(item, { quantity: 1 });
	} catch (error) {
		console.log(error);
	}
}

// ───────────────────────────────────────────────────────────────────────────
// Vista rápida (quick view): cantidad funcional, precio dinámico y feedback
// "¡Agregado! (N)" sobre el botón. El partial se carga vía AJAX (sin Vue), por
// eso la lógica vive aquí como funciones globales invocadas con onclick/oninput.
// ───────────────────────────────────────────────────────────────────────────
function qvScope(el) {
	return el.closest('[data-qv-scope]');
}

function qvClampQuantity(input) {
    const quantity = cartAmounts().quantity(input.value);
    input.value = quantity;
    input.setCustomValidity('');
    return quantity;
}

function qvSync(input) {
    try {qvClampQuantity(input);qvUpdatePrice(qvScope(input));}
    catch (error) {input.setCustomValidity(error.message);input.reportValidity();}
}

function qvStep(btn, delta) {
    const scope = qvScope(btn), input = scope.querySelector('.qv-quantity');
    try {
        const current = cartAmounts().amount(qvClampQuantity(input));
        const next = current.plus(String(delta));
        if (next.compare('0') > 0) input.value = cartAmounts().quantity(next.decimal());
        qvUpdatePrice(scope);
    } catch (error) {input.setCustomValidity(error.message);input.reportValidity();}
}

function qvUpdatePrice(scope) {
    if (!scope || scope.dataset.qvBusy === '1') return;
    const label = scope.querySelector('.qv-add-label');
    if (window.__storefront_show_prices === false || window.__storefront_show_prices === 0) {label.textContent = 'Agregar a Carrito';return;}
    const item = JSON.parse(scope.dataset.qvProduct);
    item.cantidad = qvClampQuantity(scope.querySelector('.qv-quantity'));
    const total = cartAmounts().line(item, {convert:false}).total.final(2);
    label.textContent = 'Agregar a Carrito · ' + (scope.dataset.symbol || 'Bs.') + ' ' + total;
}

function qvAddToCart(btn) {
    const scope = qvScope(btn), input = scope.querySelector('.qv-quantity');
    try {cartAddOrUpdateItem(JSON.parse(scope.dataset.qvProduct), {quantity:qvClampQuantity(input)});}
    catch (error) {input.setCustomValidity(error.message);input.reportValidity();}
}

function productsCartDropDown() {
	jQuery('.dropdown-cart-products').empty();
	jQuery('.cart-count').empty();
	let count = 0;
	let array = cartReadCart();
	count = array.length;

	array.forEach(function (element) {
		const imagePath = (element.image_small && element.image_small !== 'imagen-no-disponible.jpg')
			? '/storage/uploads/items/' + element.image_small
			: '/logo/imagen-no-disponible.jpg';
		const qty = cartAmounts().quantity(element.quantity ?? '1');

		jQuery('.dropdown-cart-products').append(
			'<div class="product">' +
				'<div class="product-details">' +
					'<h4 class="product-title"><a href="#">' + element.description + '</a></h4>' +
					'<span class="cart-product-info">' +
						'<span class="cart-product-qty">' + qty + '</span> x ' + element.sale_unit_price +
					'</span>' +
				'</div>' +
				'<figure class="product-image-container">' +
					'<a href="#" class="product-image">' +
						'<img alt="' + element.description + '" src="' + imagePath + '" />' +
					'</a>' +
					'<a href="#" onclick="remove(' + element.id + ')" class="btn-remove" title="Remove Product">' +
						'<i class="icon-cancel"></i>' +
					'</a>' +
				'</figure>' +
			'</div>'
		);
	});

	if (count > 0) {
		jQuery('.cart-count').append(count).show();
	} else {
		jQuery('.cart-count').hide();
	}
}

function calculateTotalCart() {
    if (window.__storefront_show_prices === false || window.__storefront_show_prices === 0) {
        jQuery('.cart-total-price').text('');return;
    }
    try {jQuery('.cart-total-price').text(cartAmounts().total(cartReadCart()));}
    catch (error) {jQuery('.cart-total-price').text('Importe pendiente');}
}

function logout() {
	console.log('register logout');
	jQuery.ajax({
		url: '/ecommerce/logout',
		method: 'get',
		headers: {
			'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
		},
		success: function () {
			location.reload();
		},
		error: function () {}
	});
}
