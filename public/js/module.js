
(function(Icinga) {

    var Trappola = function(module) {
        this.module = module;

        this.initialize();

        this.openedFieldsets = {};

        this.module.icinga.logger.debug('Trappola module loaded');
    };

    Trappola.prototype = {

        initialize: function()
        {
            /**
             * Tell Icinga about our event handlers
             */
            this.module.on('click', 'table.table-x-rows-collapsible .collapse-handle', this.handleCollapsibleDetailClick);
            this.module.icinga.logger.debug('Trappola module initialized');
        },

        handleCollapsibleDetailClick: function(ev)
        {
            var $el = $(ev.currentTarget);
            $el.closest('tr').find('.collapsible').toggle();
        }
    };

    Icinga.availableModules.trappola = Trappola;

}(Icinga));

