define(['jquery', 'tool_fileslist/fileslist', 'core/custom_interaction_events', 'core/templates'], function ($, FilesList, CustomEvents, Templates) {
    var SELECTORS = {
        PAGE_ITEM: '[data-region="page-item"]',
        PAGER: ['data-region="pagination"']
    };

    var registerEventListeners = function(root, filesList) {
        CustomEvents.define(root, [
            CustomEvents.events.activate
        ]);

        root.on(CustomEvents.events.activate, SELECTORS.PAGE_ITEM, function(e, data) {
            var num = +$(e.currentTarget).attr('data-page-number');
            filesList.attr('data-start', num * filesList.attr('data-limit'));

            $(e.currentTarget).parent().children('.active').removeClass('active');
            $(e.currentTarget).parent().children('[data-page-number="' + num + '"]').addClass('active');
            $(e.currentTarget).parent().children(':first-child').attr('data-page-number', num-1).removeClass('active');
            $(e.currentTarget).parent().children(':last-child').attr('data-page-number', num+1).removeClass('active');

            if (num > 0) {
                $(e.currentTarget).parent().children(':first-child').removeClass("disabled");
            } else {
                $(e.currentTarget).parent().children(':first-child').addClass("disabled");
            }

            if (num+1 == Math.round(+filesList.attr('data-num-items')/+filesList.attr('data-limit'))) {
                $(e.currentTarget).parent().children(':last-child').addClass("disabled");
            } else {
                $(e.currentTarget).parent().children(':last-child').removeClass("disabled");
            }

            FilesList.updateList(filesList);
            data.originalEvent.preventDefault();
        });
    };


    return {
        init: function(root, filesList) {
            registerEventListeners(root, filesList);

            var numPages = Math.round(+filesList.attr('data-num-items')/+filesList.attr('data-limit'));
            var pages = [{
                active: false,
                disabled: true,
                number: 0,
                label: '«'
            }];
            for (var i = 0; i < numPages; i++) {
                pages.push({
                    active: i === 0,
                    disabled: false,
                    number: i,
                    label: i+1
                });
            }
            pages.push({
                active: false,
                disabled: numPages === 1 ? true : false,
                number: 1,
                label: '»'
            });

            return Templates.render('tool_fileslist/page-items', {pages: pages})
                .done(function(html, js) {
                    Templates.replaceNodeContents(root, html, js);
                });
        }
    };
});
