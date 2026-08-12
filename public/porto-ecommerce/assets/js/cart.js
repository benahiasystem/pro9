// ───────────────────────────────────────────────────────────────────────────
// Modal de confirmación al agregar al carrito
// ───────────────────────────────────────────────────────────────────────────
var _cartConfirmState = {
	itemId: null,
	stock: 9999,
};

function cartNormalizeItem(raw) {
	let item = typeof raw === 'string' ? JSON.parse(raw) : Object.assign({}, raw);

	if (!item.currency_type_symbol) {
		item.currency_type_symbol = (item.currency_type && item.currency_type.symbol)
			? item.currency_type.symbol
			: (item.currency_type && item.currency_type['symbol'])
				? item.currency_type['symbol']
				: 'S/';
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

function cartParsePriceText(text) {
	if (!text) {
		return 0;
	}
	const cleaned = String(text).replace(/[^\d.,-]/g, '').replace(',', '.');
	const value = parseFloat(cleaned);
	return isNaN(value) ? 0 : value;
}

function cartEnrichFromProductCard(btn, item) {
	const card = btn ? btn.closest('.product') : null;
	if (!card) {
		return item;
	}

	const oldPriceEl = card.querySelector('.old-price');
	const currentPriceEl = card.querySelector('.product-price-ecommerce, .product-price');
	if (!oldPriceEl || !currentPriceEl) {
		return item;
	}

	const oldPrice = cartParsePriceText(oldPriceEl.textContent);
	const currentPrice = cartParsePriceText(currentPriceEl.textContent);

	if (oldPrice > currentPrice + 0.009) {
		item.original_price = oldPrice;
		item.sale_unit_price = currentPrice;
		item.has_discount = true;
		item.discount_percent = Math.round((1 - currentPrice / oldPrice) * 100);
	}

	return item;
}

function cartGetProductStock(item) {
	if (item.stock_max != null && item.stock_max !== '') {
		return Math.max(0, parseInt(item.stock_max, 10) || 0);
	}
	if (item.stock != null && item.stock !== '') {
		return Math.max(0, parseInt(item.stock, 10) || 0);
	}
	if (Array.isArray(item.warehouses)) {
		return item.warehouses.reduce(function (sum, w) {
			return sum + (parseFloat(w.stock) || 0);
		}, 0);
	}
	return 9999;
}

function cartGetPricing(item) {
	const symbol = item.currency_type_symbol || 'S/';
	let currentPrice = parseFloat(item.sale_unit_price);
	if (isNaN(currentPrice)) {
		currentPrice = 0;
	}

	let originalPrice = parseFloat(item.original_price);
	if (isNaN(originalPrice)) {
		originalPrice = parseFloat(item.old_price);
	}
	if (isNaN(originalPrice)) {
		originalPrice = currentPrice;
	}

	let hasDiscount = !!item.has_discount;
	let discountPercent = (item.discount_percent != null && item.discount_percent !== '')
		? parseInt(item.discount_percent, 10)
		: null;

	const campaign = window.__active_campaign;
	if (campaign && campaign.sp_discount_price) {
		const basePrice = parseFloat(item.original_price);
		const listPrice = !isNaN(basePrice) && basePrice > 0 ? basePrice : currentPrice;

		if (!hasDiscount || currentPrice >= listPrice) {
			originalPrice = listPrice;
			if (campaign.discount_type === 'percentage') {
				currentPrice = listPrice - (listPrice * (parseFloat(campaign.discount_value) / 100));
				discountPercent = Math.round(parseFloat(campaign.discount_value));
			} else {
				currentPrice = listPrice - parseFloat(campaign.discount_value);
				discountPercent = listPrice > 0
					? Math.round((1 - currentPrice / listPrice) * 100)
					: 0;
			}
			currentPrice = Math.max(0, Math.round(currentPrice * 100) / 100);
			hasDiscount = originalPrice > currentPrice + 0.009;
		}
	}

	if (!hasDiscount && originalPrice > currentPrice + 0.009) {
		hasDiscount = true;
		if (discountPercent == null || isNaN(discountPercent)) {
			discountPercent = Math.round((1 - currentPrice / originalPrice) * 100);
		}
	}

	if (!hasDiscount) {
		originalPrice = currentPrice;
		discountPercent = null;
	}

	return {
		symbol: symbol,
		originalPrice: originalPrice,
		currentPrice: currentPrice,
		hasDiscount: hasDiscount,
		discountPercent: discountPercent,
	};
}

function cartPrepareItemForStorage(item) {
	const pricing = cartGetPricing(item);
	const prepared = Object.assign({}, item, {
		sale_unit_price: pricing.currentPrice,
		original_price: pricing.originalPrice,
		has_discount: pricing.hasDiscount,
		discount_percent: pricing.discountPercent,
		quantity: parseInt(item.quantity, 10) || 1,
	});

	if (!prepared.image_small && prepared.image) {
		prepared.image_small = prepared.image;
	}

	return prepared;
}

function cartReadCart() {
	try {
		return JSON.parse(localStorage.getItem('products_cart')) || [];
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
	if (!input || !minusBtn || !plusBtn) {
		return;
	}

	let qty = parseInt(input.value, 10);
	if (isNaN(qty) || qty < 0) {
		qty = 0;
		input.value = qty;
	}

	minusBtn.disabled = qty <= 0;
	plusBtn.disabled = qty >= _cartConfirmState.stock;
}

function cartConfirmSyncQuantityToCart() {
	const input = document.getElementById('cart-confirm-qty-input');
	if (!input || _cartConfirmState.itemId == null) {
		return;
	}

	let qty = parseInt(input.value, 10);
	if (isNaN(qty) || qty < 0) {
		qty = 0;
	}
	if (_cartConfirmState.stock < 9999) {
		qty = Math.min(qty, _cartConfirmState.stock);
	}

	let array = cartReadCart();
	let index = array.findIndex(function (x) { return x.id == _cartConfirmState.itemId; });

	if (qty <= 0) {
		if (index !== -1) {
			array.splice(index, 1);
		}
	} else if (index !== -1) {
		array[index].quantity = qty;
	} else {
		return;
	}

	cartWriteCart(array);
	cartRefreshHeader();
	input.value = qty;
	cartConfirmUpdateQtyButtons();
}

function cartConfirmRenderPrices(item) {
	const pricing = cartGetPricing(item);
	const currentEl = document.getElementById('cart-confirm-price-current');
	const oldEl = document.getElementById('cart-confirm-price-old');

	if (!currentEl || !oldEl) {
		return;
	}

	if (pricing.hasDiscount) {
		currentEl.innerHTML =
			'<span class="product-price cart-confirm-current-price">' +
				pricing.symbol + ' ' + pricing.currentPrice.toFixed(2) +
			'</span>' +
			(pricing.discountPercent != null && pricing.discountPercent > 0
				? '<span class="cart-confirm-discount-badge">-' + pricing.discountPercent + '%</span>'
				: '');

		oldEl.innerHTML =
			'<span class="old-price cart-confirm-old-price">' +
				pricing.symbol + ' ' + pricing.originalPrice.toFixed(2) +
			'</span>';
		oldEl.style.display = 'block';
	} else {
		currentEl.innerHTML =
			'<span class="product-price cart-confirm-current-price">' +
				pricing.symbol + ' ' + pricing.currentPrice.toFixed(2) +
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

	if (!minusBtn || minusBtn.dataset.bound === '1') {
		return;
	}

	minusBtn.dataset.bound = '1';

	minusBtn.addEventListener('click', function () {
		let qty = parseInt(input.value, 10) || 0;
		if (qty > 0) {
			input.value = qty - 1;
			cartConfirmSyncQuantityToCart();
		}
	});

	plusBtn.addEventListener('click', function () {
		let qty = parseInt(input.value, 10) || 0;
		if (qty < _cartConfirmState.stock) {
			input.value = qty + 1;
			cartConfirmSyncQuantityToCart();
		}
	});

	input.addEventListener('change', function () {
		let qty = parseInt(input.value, 10);
		if (isNaN(qty) || qty < 0) {
			qty = 0;
		}
		if (_cartConfirmState.stock < 9999) {
			qty = Math.min(qty, _cartConfirmState.stock);
		}
		input.value = qty;
		cartConfirmSyncQuantityToCart();
	});

	if (modal) {
		jQuery(modal).off('hidden.bs.modal.cartConfirm').on('hidden.bs.modal.cartConfirm', function () {
			cartConfirmSyncQuantityToCart();
			_cartConfirmState.itemId = null;
		});
		jQuery(modal).off('shown.bs.modal.cartConfirm').on('shown.bs.modal.cartConfirm', function () {
			cartConfirmUpdateQtyButtons();
		});
	}
}

function showCartConfirmModal(item, mode) {
	item = cartNormalizeItem(item);
	mode = mode || 'added';

	const prepared = cartPrepareItemForStorage(item);
	const array = cartReadCart();
	const found = array.find(function (x) { return x.id == prepared.id; });
	const displayItem = cartConfirmDisplayItem(prepared, found);
	const quantity = found ? (parseInt(found.quantity, 10) || 1) : (parseInt(prepared.quantity, 10) || 1);

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
	if (options.triggerEl) {
		const parsed = cartParseProductFromButton(options.triggerEl);
		if (parsed) {
			item = parsed;
		}
		item = cartEnrichFromProductCard(options.triggerEl, cartNormalizeItem(item));
	} else {
		item = cartNormalizeItem(item);
	}
	const prepared = cartPrepareItemForStorage(item);
	const array = cartReadCart();
	const found = array.find(function (x) { return x.id == prepared.id; });
	const addQty = parseInt(options.quantity, 10) || parseInt(prepared.quantity, 10) || 1;
	let mode = 'added';

	if (found) {
		if (options.replaceQuantity) {
			found.quantity = addQty;
		} else {
			found.quantity = (parseInt(found.quantity, 10) || 1) + addQty;
		}
		found.sale_unit_price = prepared.sale_unit_price;
		found.original_price = prepared.original_price;
		found.has_discount = prepared.has_discount;
		found.discount_percent = prepared.discount_percent;
		found.stock = cartGetProductStock(prepared);
		mode = options.mode || 'exists';
	} else {
		prepared.quantity = addQty;
		prepared.stock = cartGetProductStock(prepared);
		array.push(prepared);
	}

	const stock = cartGetProductStock(prepared);
	const target = found || prepared;
	if (stock < 9999 && target.quantity > stock) {
		target.quantity = stock;
	}

	cartWriteCart(array);
	cartRefreshHeader();

	const displayItem = found || prepared;
	showCartConfirmModal(displayItem, mode);

	return displayItem;
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
	let val = parseInt(input.value, 10);
	if (isNaN(val) || val < 1) val = 1;
	input.value = val;
	return val;
}

function qvSync(input) {
	qvClampQuantity(input);
	qvUpdatePrice(qvScope(input));
}

function qvStep(btn, delta) {
	let scope = qvScope(btn);
	let input = scope.querySelector('.qv-quantity');
	let val = parseInt(input.value, 10);
	if (isNaN(val)) val = 1;
	val += delta;
	if (val < 1) val = 1;
	input.value = val;
	qvUpdatePrice(scope);
}

function qvUpdatePrice(scope) {
	if (!scope || scope.dataset.qvBusy === '1') return;
	let input = scope.querySelector('.qv-quantity');
	let label = scope.querySelector('.qv-add-label');
	let qty = parseInt(input.value, 10) || 1;
	let unit = parseFloat(scope.dataset.unitPrice) || 0;
	let symbol = scope.dataset.symbol || 'S/';
	label.textContent = 'Agregar a Carrito · ' + symbol + ' ' + (unit * qty).toFixed(2);
}

function qvAddToCart(btn) {
	try {
		let scope = qvScope(btn);
		let input = scope.querySelector('.qv-quantity');
		let qty = qvClampQuantity(input);

		let item = JSON.parse(scope.dataset.qvProduct);
		cartAddOrUpdateItem(item, { quantity: qty });
	} catch (e) {
		console.log(e);
	}
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
		const qty = element.quantity || 1;

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
	let array = cartReadCart();
	let total = 0;
	array.forEach(function (element) {
		const qty = parseInt(element.quantity, 10) || 1;
		total += parseFloat(element.sale_unit_price) * qty;
	});

	jQuery('.cart-total-price').empty();
	jQuery('.cart-total-price').append(total.toFixed(2));
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
