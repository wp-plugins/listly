jQuery(document).ready(function($)
{
	$('#ListlyAdminAuthStatus').click(function(e)
	{
		e.preventDefault();

		var Key = $(this).prev('input').val();
		var ElmMsg = $(this).next('span');

		ElmMsg.html('Loading...');

		$.post(ajaxurl, {'action': 'AJAXPublisherAuth', 'nounce': ListlyNounce, 'Key': Key}, function(data)
		{
			ElmMsg.html(data);
		});
	});


	$('#ListlyAdminListAdd').click(function(e)
	{
		e.preventDefault();

		$('<div id="ListlyAdminListDialog"><p>Loading...</p></div>').load(ajaxurl, {'action': 'AJAXListAdd', 'nounce': ListlyNounce}).dialog(
		{
			title: 'Listly',
			autoOpen: true,
			draggable: true,
			modal: true,
			resizable: false,
			width: 600,
			height: 550,
			close: function(event, ui)
			{
				$(this).remove();
			}
		});
	});


	$('.listly-form-list-add .button-primary').live('click', function(e)
	{
		e.preventDefault();

		$('.listly-form-message-list-add').html('<span class="strong">Loading...</span>');

		var PostData = $('.listly-form').serialize();

		$.post(ajaxurl, PostData, function(data)
		{
			if (data.status == 'ok')
			{
				$('#ListlyAdminYourList').prepend("<p>"+$('.listly-form [name^="ListlyList[title]"]').val()+"<br /><a class='ListlyAdminListEmbed' href='#' data-Id='"+data.list+"'>ShortCode</a> <a class='ListlyAdminListInfo' href='#' data-Id='"+data.list+"'>Edit</a></p>");

				$('.listly-wrap-list-add').html('<div><p class="strong">Loading...</p></div>').load(ajaxurl, {'action': 'AJAXListInfo', 'nounce': ListlyNounce, 'ListId': data.list, 'Message': data.message});
			}
			else
			{
				$('.listly-form-message-list-add').html('<span class="error">'+data.message+'</span>');
			}
		}, 'json');
	});


	$('a.ListlyAdminListInfo').live('click', function(e)
	{
		e.preventDefault();

		$('<div id="ListlyAdminListDialog"><p>Loading...</p></div>').load(ajaxurl, {'action': 'AJAXListInfo', 'nounce': ListlyNounce, 'ListId': $(this).attr('data-Id')}).dialog(
		{
			title: 'Listly',
			autoOpen: true,
			draggable: true,
			modal: true,
			resizable: false,
			width: 600,
			height: 550,
			close: function(event, ui)
			{
				$(this).remove();
			}
		});
	});

	$('a.ListlyAdminListEmbed').live('click', function(e)
	{
		e.preventDefault();

		//tinyMCE.execCommand('mceInsertContent', false, 'Text');
		//tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, 'Text', false)

		if (window.tinyMCE.activeEditor != null && window.tinyMCE.activeEditor.isHidden() == false)
		{
			tinyMCE.execCommand('mceInsertContent', false, '<p>[listly id="'+$(this).attr('data-Id')+'" theme="'+ListlySettings[1]+'" layout="'+ListlySettings[2]+'" numbered="'+ListlySettings[3]+'" image="'+ListlySettings[4]+'" items="'+ListlySettings[5]+'"]</p>');
		}
		else
		{
			$('#content').val($('#content').val() + "\r\n\r\n" + '[listly id="'+$(this).attr('data-Id')+'" theme="'+ListlySettings[1]+'" layout="'+ListlySettings[2]+'" numbered="'+ListlySettings[3]+'" image="'+ListlySettings[4]+'" items="'+ListlySettings[5]+'"]');
		}
	});


	$('a.ListlyAdminListAddItems').live('click', function(e)
	{
		e.preventDefault();

		$(this).closest('.listly-wrap-list-info-box').html('<p>Loading...</p>').load(ajaxurl, {'action': 'AJAXItemAdd', 'nounce': ListlyNounce, 'ListId': $(this).attr('data-Id')});
	});

	$('.listly-form-item-add .button-primary').live('click', function(e)
	{
		e.preventDefault();

		Elm = $(this);

		$('.listly-form-message-item-add').html('<span class="strong">Loading...</span>');

		var PostData = $('.listly-form').serialize();

		$.post(ajaxurl, PostData, function(data)
		{
			if (data.status == 'ok')
			{
				Elm.closest('.listly-wrap-list-info-box').html('<p>Loading...</p>').load(ajaxurl, {'action': 'AJAXItemInfo', 'nounce': ListlyNounce, 'ListId': Elm.attr('data-Id')});
			}
			else
			{
				$('.listly-form-message-item-add').html('<span class="error">'+data.message+'</span>');
			}
		}, 'json');
	});

	$('.listly-form-item-add .button-secondary, .listly-wrap .button-secondary').live('click', function(e)
	{
		e.preventDefault();

		$('#ListlyAdminListDialog').dialog('close');
	});

});