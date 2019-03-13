{namespace name="frontend/listing/listing_actions"}

{block name="frontend_listing_filter_facet_radio"}
    {$type = 'radio'}
    {$listingMode = {config name="listingMode"}}
    {if $listingMode == 'filter_ajax_reload'}
        {$type = 'value-list-single'}
    {/if}

    {include file='frontend/listing/filter/_includes/filter-multi-selection.tpl' filterType=$type}
{/block}
