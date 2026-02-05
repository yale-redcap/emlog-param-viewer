

EMLPV.log_id = null;

EMLPV.ajax = function( data, callback ) {

    $.ajax({
        url: EMLPV.serviceUrl,
        type: "POST",
        dataType: "json",                     
        data: data
    })
    .done( callback )
    .fail(function(jqXHR, textStatus, errorThrown) 
    {
        // Glean what we can from textStatus
        let msg;
        if (textStatus === "parsererror") msg = "Error parsing the server response.";
        else if (textStatus === "timeout") msg = "The request timed out. Please try again.";
        else if (textStatus === "abort") msg = "The request was cancelled.";
        else msg = "A network/server (AJAX) error occurred.";

        msg += `\n\nError details: ${errorThrown || 'Unknown error'}`;

        alert(msg);

        console.error('AJAX request failed:', textStatus, errorThrown, jqXHR);
    });
};

/**
 * Generates and displays a dialog with the given content item (either a log message or a log parameter value).
 * 
 * @param {*} response              the AJAX response object    
 * @param {*} content_item_name     either 'message' or 'param_name'
 * @param {*} dialogTitle           the dialog title (currently 'Log Message' or 'Log Entry Parameter Value')
 * @returns 
 */
EMLPV.buildDialog = function( response, content_item_name, dialogTitle ){

    if ( response.status !== 'success' ){

        console.error(`Error fetching ${content_item_name}: ${response.status_message}`, response );
        alert(`Error fetching ${content_item_name}.`);
        return;
    }

    // destroy any leftover EMLPV dialog content divs to avoid DOM clutter
    $(`div[id^="emlpv-"]`).remove();

    let content = '';

    let dlgId = '';

    let tableHtml = '';
    let title = dialogTitle;

    if ( response.status !== 'success' ){

        dlgId = `emlpv-999999-error`;
        content = response.status_message;
    }
    else {

        dlgId = `emlpv-${response.data.log_id}-${content_item_name}`;
        tableHtml = EMLPV.logInfoTableHtml( response.data, content_item_name );
        content = tableHtml 
        + '<pre class="emlpv-scrolling-container" style="white-space: pre-wrap; word-break: normal; overflow-wrap: anywhere;">' 
        + response.data[content_item_name]
        + '</pre>'
        ;
    }

    simpleDialog(
        content, // inner HTML content
        title, // title
        dlgId, // content wrapper ID
        1200 // width
    );

    /**
     * The jQuery UI dialog, at least as implemented by the simpleDialog() function in REDCap,
     * initially sizes the dialog based on the content length, potentially exceeding the viewport.
     * 
     * Therefore, the content is wrapped in a scrolling element, with a max height set (400px).
     * The dialog does not stretch vertically beyond the viewport, and the content wrapper is sized accordingly.
     * 
     * However, the resizing behavior does not automatically adjust the content area to fit within the dialog 
     * in such a way as to avoid nested scrollbars.
     * 
     * Here we add a resize handler to adjust the content area to fit properly within the dialog
     * whenever the dialog wrapper is resized. 
     * 
     * As a further measure, the content wrapper div is set to not scroll, in case my content resizing arithmetic fails.
     */
    
    const $contentWrapper = $(`div#${dlgId}`);
    const $dialog = $contentWrapper.closest('.ui-dialog');
    const $innerContentWrapper = $contentWrapper.find('div, pre').first();

    $contentWrapper.addClass('emlpv-no-scrolling-container'); // disable outer div scrolling

    $dialog.on('resize', function() {
        const tableHt = $contentWrapper.find('table.emlpv-log-info-table').outerHeight(true) || 0;
        const contentWrapperHt = $contentWrapper.innerHeight(); // seems to resize correctly, so we fit the content inside it
        const contentHt = contentWrapperHt - tableHt - 30; // padding/margin/fudge factor
        $innerContentWrapper.css('max-height', contentHt + 'px');
    });

    $dialog.trigger('resize'); // initial sizing
};

/**
 * The log info table HTML (the header for the dialog content).
 * 
 * @param {*} logData 
 * @param {*} content_item_name 
 * @returns 
 */
EMLPV.logInfoTableHtml = function( logData, content_item_name ){

    let html = '<table id="emlpv-log-info-table" class="emlpv-log-info-table"><tbody>';

    // row 1: log_id
    html += `<tr><td>log_id</td><td>${logData.log_id}</td></tr>`;
    // row 2: timestamp
    html += `<tr><td>timestamp</td><td>${logData.timestamp}</td></tr>`;
    // row 3: module_name
    html += `<tr><td>module</td><td>${logData.module_name}</td></tr>`;

    // add a row for project_id if available
    if ( logData.project_id !== null && parseInt(logData.project_id) > 0 ){
        html += `<tr><td>project_id</td><td>${logData.project_id}</td></tr>`;
    }

    // add a row for record if available
    if ( logData.record && logData.record !== 'undefined' ){
        html += `<tr><td>record</td><td>${logData.record}</td></tr>`;
    }

    // add a row for user_name if available
    if ( logData.user_name && logData.user_name !== 'undefined' ){
        html += `<tr><td>user</td><td>${logData.user_name}</td></tr>`;
    }

    if ( content_item_name === 'param_value' ) {

        // row K-1: message
        html += `<tr><td>message</td><td>${logData.message}</td></tr>`;

        // row K: param_name
        html += `<tr><td>parameter</td><td><strong>${logData.param_name}</strong></td></tr>`;
    }

    html += '</tbody></table>';

    return html;
};

/**
 * Sets EMLPV.log_id based on the table row containing the given element.
 * Uses DataTables API to get the log_id from the hidden first column.
 * Calculates the overall row index based on (1) the data-row-index attribute if present,
 * or (2) the DataTables pagination controls and the row number within the current page.
 * 
 * @param {*} element 
 * @returns 
 */
EMLPV.setLogIdFromElement = function( element ){

    const tr = element.closest('tr');

    const table = tr.closest('table');

    // FIRST ATTEMPT: use data-row-index attribute on the element

    const rowIndexAttrib = element.getAttribute("data-row-index");

    if (rowIndexAttrib !== null) {
        const value = Number(rowIndexAttrib);
        if (Number.isInteger(value)) {
            EMLPV.log_id = $(table).DataTable().cell(value, 0).data() ?? null;
            return  EMLPV.log_id;
        }
    }

    // SECOND ATTEMPT: calculate the row index based on DataTables pagination controls

    let dataTablesPageLength = 0;

    let pageNumber = 1;

    // The page length select element ("Show N entries").
    // This appears to always be on the DOM, even for short lists, but just in case, we check for its existence.
    const dataTablesPageLengthSelect = document.querySelector('div.dataTables_length select');

    // Set the page length, defaulting to 0 if the select element is not found
    if ( dataTablesPageLengthSelect ){
        dataTablesPageLength = parseInt( dataTablesPageLengthSelect.value );
    }

    // fall back to 0 if invalid
    if ( isNaN(dataTablesPageLength) || dataTablesPageLength < 0 ){
        dataTablesPageLength = 0;
    }

    // calculate the current page number only if we have a valid page length
    if ( dataTablesPageLength > 0 ){

        // The page number input element ("Page J of K").
        // If the list is too small for pagination, this element will be on the DOM, but not visible.
        const pageNumberInput = document.querySelector('input.paginate_input[type=text]');

        // if page number input element is on the DOM and is visible, get its value
        if ( pageNumberInput && pageNumberInput.offsetParent !== null ){
            pageNumber = parseInt( pageNumberInput.value );
        }

        // fall back to page 1 if invalid
        if ( isNaN(pageNumber) || pageNumber < 1 ){
            pageNumber = 1;
        }
    }

    // row index within the table
    const tr_row_number = Array.prototype.indexOf.call(tr.parentNode.children, tr);

    // calculate the overall row index in the full dataset
    const row_index = (pageNumber - 1) * dataTablesPageLength + tr_row_number;

    // first column is log_id, although it is hidden from view
    EMLPV.log_id = $(table).DataTable().cell(row_index, 0).data() ?? null;

    return EMLPV.log_id;
}

$( function () {

    const messageCellSelector           = 'div#external-module-logs-wrapper table tr td.message-column';
    const showParametersButtonSelector  = 'div#external-module-logs-wrapper table tr td button.show-parameters';
    const parameterValueCellSelector    = 'table.log-parameters tr td:nth-child(2)'; // first TD is param_name, second is param_value

    /**
     * set the tooltip for log message cells
     */
    $(document)
        .off('mouseenter.emlpv.log-message-tooltip', messageCellSelector)
        .on('mouseenter.emlpv.log-message-tooltip', messageCellSelector, function(e) {

            const $el = $(this);

            // if tooltip already initialized, do nothing
            if ( $el.data('emlpvTooltipInit') ){
                return;
            }

            $el.data('emlpvTooltipInit', true )
               .attr('title', "Click to view the full content of the em log message." )
            ;
        });

    /**
     * set the tooltip for parameter value cells
     */
    $(document)
        .off('mouseenter.emlpv.log-parameter-tooltip', parameterValueCellSelector)
        .on('mouseenter.emlpv.log-parameter-tooltip', parameterValueCellSelector, function(e) {

            const $el = $(this);

            const param_name = $el.siblings('td').first().text();

            // if tooltip already initialized, do nothing
            if ( $el.data('emlpvTooltipInit') ){
                return;
            }

            $el.data('emlpvTooltipInit', true )
               .attr('title', `Click to view the full content of the em log parameter '${param_name}'.` )
            ;
        });

    /**
     * Click handler for log message cells.
     * 
     * When a log message cell is clicked, an AJAX request is made to fetch the full
     * log message from the server, and display it in a new dialog.
     */

    $(document)
        .off('click.emlpv.log-message', messageCellSelector)
        .on('click.emlpv.log-message', messageCellSelector, function(e) {

        if ( !EMLPV.setLogIdFromElement( this ) ) {
            console.error('Could not determine log_id for log message cell click.');
            return;
        }

        EMLPV.ajax( 
           {
                log_id: EMLPV.log_id,
                item_type: 'message',
           },
           (response) => EMLPV.buildDialog( response, 'message', 'Log Message' )
        );
    });

    /**
     * Click handler for "Show Parameters" buttons.
     * 
     * When a 'show parameters' button is clicked, the log id from that row is stored
     * in EMLPV.log_id for use by the parameter value click handler.
     */

    $(document)
        .off('click.emlpv.show-parameters', showParametersButtonSelector)
        .on('click.emlpv.show-parameters', showParametersButtonSelector, function(e) {

        if ( !EMLPV.setLogIdFromElement( this ) ) {
            console.error('Could not determine log_id for Show Parameters button click.');
            return;
        }
    });

    /**
     * Click handler for parameter value cells.
     * 
     * When a parameter value cell is clicked, an AJAX request is made to fetch the full
     * parameter value from the server, and display it in a new dialog.
     */

    $(document)
        .off('click.emlpv.log-parameter', parameterValueCellSelector)
        .on('click.emlpv.log-parameter', parameterValueCellSelector, function(e) {

        if ( EMLPV.log_id === null ) {
            console.error('Could not determine log_id for log parameter cell click.');
            return;
        }

        const cells = this.closest('tr').querySelectorAll('td'); // get all TDs in the row

        const param_name = cells[0].innerText; // first TD is param_name

        EMLPV.ajax( 
            {
                log_id: EMLPV.log_id,
                item_type: 'parameter',
                param_name: param_name
            },
            (response) => EMLPV.buildDialog( response, 'param_value', 'Log Entry Parameter Value' )
        );
    });
});
