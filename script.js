jQuery(document).ready(function($)
{
	$('#ListlyAdminAuthCheck').click(function(e)
	{
		e.preventDefault();
		ListlyAuthStatus();
	});

	function ListlyAuthStatus()
	{
		var PubKey = $('input[name="PublisherKey"]').val(), AuthMsg = $("#ListlyAdminAuthStatus"), CheckButton = $("#ListlyAdminAuthCheck");

		AuthMsg.html('');
		CheckButton.text("Checking...").attr("disabled", "disabled")

		$.post(ajaxurl, {'action': 'ListlyAJAXPublisherAuth', 'nounce': Listly.Nounce, 'Key': PubKey}, function(data)
		{
			AuthMsg.html(data);
			CheckButton.text("Check Status").removeAttr("disabled")
		});
	}

  if ($('input[name="PublisherKey"]').val() != "") {
		ListlyAuthStatus(); 	
  }

	$('input[name="ListlyAdminListSearch"]').bind('keyup', function(event)
	{
		var ElmValue = $(this).val();
		var Container = $('#ListlyAdminYourList');
		var SearchType = $('input[name="ListlyAdminListSearchType"]:checked').val();

		if (ElmValue.length)
		{
			$('.ListlyAdminListSearchClear').show();
		}
		else
		{
			$('.ListlyAdminListSearchClear').hide();
		}

		if (ElmValue == '' && SearchType == 'publisher')
		{
			ListlyAdminYourList();
		}

		if (ElmValue.length > 2)
		{
			Container.html('<p>Loading...</p>');

			$.ajax
			({
				type: 'POST',
				url: Listly.SiteURL + 'autocomplete/list.json',
				data: {'term': ElmValue, 'key': Listly.Key, 'type': SearchType},
				jsonp: 'callback',
				//jsonpCallback: 'jsonCallback',
				contentType: 'application/json',
				dataType: 'jsonp',
				success: function(data)
				{
					if (data.status == 'ok')
					{
						Container.empty();

						if (jQuery.isEmptyObject(data.results))
						{
							Container.append('<p>No lists found! <a target="_blank" href="http://list.ly?trigger=newlist">Make a list now?</a></p>');
						}
						else
						{
							ListlyAdminPopulateList(data.results)
						}
					}
					else
					{
						Container.html(data.message);
					}
				},
				error: function(jqXHR, textStatus, errorThrown)
				{
					Container.html('<p>Error: '+errorThrown+'</p>');
				}
			});
		}
	});


	$('.ListlyAdminListSearchClear').click(function(e)
	{
		e.preventDefault();

		$('.ListlyAdminListSearchClear').hide();
		$('input[name="ListlyAdminListSearch"]').val('').focus();

		var SearchType = $('input[name="ListlyAdminListSearchType"]:checked').val();

		if (SearchType == 'publisher')
		{
			ListlyAdminYourList();
		}
	});


	$('input[name="ListlyAdminListSearchType"]').click(function(e)
	{
		$('input[name="ListlyAdminListSearch"]').trigger('keyup');
	});

	function ListlyAdminPopulateList(lists){
		var Container = $('#ListlyAdminYourList');

		$(lists).each(function(i)
		{
			var item = '<p class="ListlyAdminItem"> \
			             <img class="ListlyAdminItemImage" src="'+lists[i].image+'" alt="" /> \
			               <a class="ListlyAdminItemEmbed" target="_blank" href="http://list.ly/preview/'+lists[i].list_id+'?key='+Listly.Key+'&source=wp_plugin" title="Customize and get short code"> \
			                 <span class="dashicons dashicons-editor-code"></span> \
			                 <span>GET SHORT CODE</span> \
			               </a> \
			               <span class="ListlyAdminItemTitle">' + lists[i].title + ' \
			                 <a class="dashicons dashicons-external" target="_blank" href="http://list.ly/l/'+lists[i].list_id+'?source=wp_plugin" title="See list on List.ly"> \
			                 </a> \
			               </span> \
			            </p>'
			Container.append(item);
		});

	}

	function ListlyAdminYourList()
	{
		window.clearTimeout(ListlyAdminYourListTimer)

		var Container = $('#ListlyAdminYourList');

		Container.html('<p>Loading...</p>');

		$.ajax
		({
			type: 'POST',
			url: Listly.SiteURL + 'publisher/lists',
			data: {'key': Listly.Key},
			jsonp: 'callback',
			//jsonpCallback: 'jsonCallback',
			contentType: 'application/json',
			dataType: 'jsonp',
			success: function(data)
			{
				if (data == '')
				{
					Container.html('<p>Connection error. Retrying in 1 minute...<a id="ListlyAdminYourListReload" href="#">try now</a></p>');

					var ListlyAdminYourListTimer = window.setTimeout(ListlyAdminYourList, 60000);
				}
				else if (data.status == 'ok')
				{
					Container.empty();

					if (jQuery.isEmptyObject(data.lists))
					{
						Container.append('<p>No lists found! <a target="_blank" href="http://list.ly?trigger=newlist">Make a list now?</a></p>');
					}
					else
					{
						ListlyAdminPopulateList(data.lists)
					}
				}
				else if (data.message != '')
				{
					Container.html(data.message);
				}
				else
				{
					Container.html('<p>Connection error. Retrying in 1 minute...<a id="ListlyAdminYourListReload" href="#">try now</a></p>');

					var ListlyAdminYourListTimer = window.setTimeout(ListlyAdminYourList, 60000);
				}
			},
			error: function(jqXHR, textStatus, errorThrown)
			{
				Container.html('<p>Connection error. Retrying in 1 minute...<a id="ListlyAdminYourListReload" href="#">try now</a></p>');

				var ListlyAdminYourListTimer = window.setTimeout(ListlyAdminYourList, 60000);
			}
		});
	}

	if ($('#ListlyAdminYourList').length)
	{
		var ListlyAdminYourListTimer;

		ListlyAdminYourList();

		$('#ListlyAdminYourList').on('click', '#ListlyAdminYourListReload', function(e)
		{
			e.preventDefault();

			ListlyAdminYourList();
		});
	}

});