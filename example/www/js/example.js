/* Reset the iframe and prepare the screen for new orders */
function reset() {
	$('.iframe_container > iframe').addClass('hidden');
	$('.failure').addClass('hidden');
}

/* Display an error message */
function failure(failing, errortext) {
	$('.failure .failing').text(failing);
	$('.failure .errortext').text(errortext);
	$('.failure').removeClass('hidden');
}

/* Use the /clear_orders PHP AJAX call to purge all the information about known
 * orders. Also clears the table from order data as the update_orders() call
 * will only add new rows, never remove old ones */
function clear_orders() {
	reset();
	$.ajax({
		contentType: 'application/json; charset=UTF-8',
		data: null,
		dataType: 'json',
		error: function (jqXHR, textStatus, errorThrown) {
			failure('Failed to get clear order data', errorThrown.statusText);
		},
		success: function (data, textStatus, jqXHR) {
			$('.orders table tr.order').remove();
			$('.orders').addClass('hidden');
		},
		type: 'GET',
		url: '/php/example.php/clear_orders',
	});
}


/* Use the /deposit call to create a trustly order and display the resulting
 * iframe if successful */
function deposit() {
	reset();

	var amount = $('#amount').val();
	var currency = $('#currency').val();
	$.ajax({
		contentType: 'application/json; charset=UTF-8',
		data: {
			'amount': amount,
			'currency': currency
		},
		dataType: 'json',
		error: function (jqXHR, textStatus, errorThrown) {
			failure('Failed to make deposit call', textStatus);
		},
		success: function (data, textStatus, jqXHR) {
			if(data.result == 'ok') {
				$('.iframe_container > iframe').prop('src', data.url).removeClass('hidden');
				$('.iframe_container').removeClass('hidden');
			} else {
				failure('Failed to make deposit call', data.error);
			}
		},
		type: 'GET',
		url: '/php/example.php/deposit',
	});
}


/* Helper (redursive) function for _build_notification_td(), builds a dl with
 * informaiton about the individual fields in the notification */
function _build_object_dl(data) {
	var dl = document.createElement('DL');
	for (var property in data) {
		if (data.hasOwnProperty(property) && property != 'datestamp') {
			var dd = document.createElement('DD');
			var dt = document.createElement('DT');
			dt.innerHTML = property;
			if(typeof(data[property]) == 'object') {
				var idl = _build_object_dl(data[property]);
				dd.appendChild(idl);
			} else {
				dd.innerHTML = data[property];
			}
			dl.appendChild(dt);
			dl.appendChild(dd);
		}
	}
	return dl;
}


/* Helper function for update_orders(), builds a td cell containing all the
 * information about a notification.*/
function _build_notification_td(td, data) {
	td.children().remove();

	if(!data) {
		return ;
	}

	td.text(data.datestamp);
	var dl = _build_object_dl(data);
	td.append($(dl));
}


/* Update the order information table based upon information from the backend.
 * Use the /orders PHP AJAX call to obtain all information about the orders and
 * then dynamically build the table with information. */
function update_orders() {
	$.ajax({
		contentType: 'application/json; charset=UTF-8',
		data: null,
		dataType: 'json',
		error: function (jqXHR, textStatus, errorThrown) {
			failure('Failed to get order data', jqXHR.statusText);
		},
		success: function (data, textStatus, jqXHR) {
			if(data.result == 'ok') {
				for(var i in data.orders) {
					var order = data.orders[i];

					var $row = $('.orders table tr[orderid=' + order.orderid + ']');
					if(!$row.length) {
						var tr = document.createElement('TR');
						tr.setAttribute('orderid', order.orderid);
						tr.className = 'order';

						var columns = ['orderid', 'amount', 'created', 'account', 'pending', 'credit', 'cancel', 'debit'];

						for(var i in columns) {
							var td = document.createElement('TD');
							td.className = columns[i];
							tr.appendChild(td);
						}
						$row = $(tr);
						$('.orders table').append($row);
					}
					var $row_td = $row.find('td');
					$row_td.eq(0).text(order.orderid);

					var amount = '-';
					if(typeof(order.amount) != 'undefined' && order.amount != null) {
						amount = order.amount;
					}
					if(typeof(order.currency) != 'undefined' && order.currency != null) {
						amount = amount + ' ' + order.currency;
					}
					$row_td.eq(1).text(amount);
					$row_td.eq(2).text(order.created)?order.created:'';

					_build_notification_td($row_td.eq(3), order.account);
					_build_notification_td($row_td.eq(4), order.pending);
					_build_notification_td($row_td.eq(5), order.cancel);
					_build_notification_td($row_td.eq(6), order.credit);
					_build_notification_td($row_td.eq(7), order.debit);
				}
				if(data.orders.length) {
					$('.orders').removeClass('hidden');

					$('.orders table tr.order').click(function() {
						$(this).toggleClass('show-extra');

					});
				}
			}
		},
		type: 'GET',
		url: '/php/example.php/orders',
	});
}


/* Use the /extensions call in the PHP AJAX to make sure all the needed PHP
 * extensions are loaded. If so, set the main timer that will poll for order
 * information. Otherwise display an error message with missing module
 * information */
function check_extensions() {
	reset();
	$.ajax({
		contentType: 'application/json; charset=UTF-8',
		data: null,
		dataType: 'json',
		error: function (jqXHR, textStatus, errorThrown) {
			failure('Failed to get PHP extension information', errorThrown.statusText);
		},
		success: function (data, textStatus, jqXHR) {
			var missing = false;
			for(extension in data.extensions) {
				if(!data.extensions[extension]) {
					missing = true;
				}
			}
			if(missing) {
				var $ul = $('#missing-modules > ul');
				for(extension in data.extensions) {
					var text = extension + ': ' + (data.extensions[extension]?'installed':'missing');

					var li = document.createElement('LI');
					li.innerHTML = text;
					$ul.append(li);
				}

				$('.extensions-ok').addClass('hidden');
				$('.extensions-not-ok').removeClass('hidden');
			} else {
				update_orders();
				window.setInterval(update_orders, 3000);
			}
		},
		type: 'GET',
		url: '/php/example.php/extensions',
	});
}


/* Setup the basic click handlers */
$(document).ready(function() {
	$('#deposit-button').click(deposit);
	$('#clear-order-data-button').click(clear_orders);
	check_extensions();
});
