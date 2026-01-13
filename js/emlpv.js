


EMLPV.ajax = function( request, data, callback ) {

    data.request = request;
    data.redcap_csrf_token = redcap_csrf_token;

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
 * Callback for fetchLogParameterValue AJAX request.
 * Creates and displays a dialog with the full parameter value.
 * 
 * @param {} response 
 */
EMLPV.fetchLogParameterValueCallback = function( response ){

    console.log('fetchLogParameterValue response:', response);

    EMLPV.buildDialog( response, 'param_value', 'Log Entry Parameter Value' );
}

/**
 * Callback for fetchLogMessage AJAX request.
 * Creates and displays a dialog with the full log message.
 * 
 * @param {} response 
 */
EMLPV.fetchLogMessageCallback = function( response ){

    console.log('fetchLogMessage response:', response);

    EMLPV.buildDialog( response, 'message', 'Log Message' );
}

EMLPV.buildDialog = function( response, content_item_name, dialogTitle ){

    // destroy any leftover dialog content divs to avoid DOM clutter
    $(`div[id^="emlpv-"]`).remove();

    let content = '';

    let dlgId = '';

    //response.data.record = 'fooDeluxe'; // for testing

    let tableHtml = '';
    let title = dialogTitle;

    if ( response.status !== 'success' ){

        dlgId = `emlpv-999999-error`;
        content = response.status_message;
    }
    else {

        dlgId = `emlpv-${response.data.log_id}-${response.data.content_item_name}`;
        tableHtml = EMLPV.logInfoTableHtml( response.data, content_item_name );
        content = tableHtml 
        + '<pre class="emlpv-scrolling-container" style="white-space: pre-wrap; word-break: normal; overflow-wrap: anywhere;">' 
        + response.data[ content_item_name ]
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
     * Therefore, the content is wrapped in a scrolling pre/div, with a max height set (400px).
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
 * Extract as much data as possible from the selected main table row.
 * 
 * @param {*} cells 
 * @returns 
 */
EMLPV.logDataFromRowCells = function( cells ){
    
    let logData = {
        timestamp: cells[0].innerText,
        module_name: cells[1].innerText,
    };

    if (cells.length === 7) {
        logData.project_id = cells[2].innerText;
        logData.record = cells[3].innerText;
        logData.message = cells[4].innerText;
        logData.user_name = cells[5].innerText;
    } else {
        logData.project_id = null;
        logData.record = cells[2].innerText;
        logData.message = cells[3].innerText;
        logData.user_name = cells[4].innerText;
    }

    return logData;
}

$( function () {

    /**
     * Click handler for "Show Parameters" buttons.
     * 
     * When a 'show parameters' button is clicked, the log data from that row is stored
     * in EMLPV.logData for use by the parameter value click handler.
     */

    $(document)
        .off('click.emlpv.show-parameters', 'table tr td button.show-parameters')
        .on('click.emlpv.show-parameters', 'table tr td button.show-parameters', function(e) {

        const button = $(this)[0]; // using the DOM element directly in this handler

        // bail if the row does not have 6 or 7 columns (not a log entry row)
        const cells = button.closest('tr').querySelectorAll('td');
        if (cells.length !== 6 && cells.length !== 7) return;

        // extract log data from the row cells, for use by parameter value click handler
        EMLPV.logData = EMLPV.logDataFromRowCells(cells);
    });

    /**
     * Click handler for parameter value cells.
     * 
     * When a parameter value cell is clicked, an AJAX request is made to fetch the full
     * parameter value from the server, and display it in a new dialog.
     */

    $(document)
        .off('click.emlpv.log-parameter', 'table.log-parameters tr td:nth-child(2)')
        .on('click.emlpv.log-parameter', 'table.log-parameters tr td:nth-child(2)', function(e) {

        const td = $(this)[0]; // using the DOM element directly in this handler

        const cells = td.closest('tr').querySelectorAll('td'); // get all TDs in the row

        EMLPV.logData.param_name = cells[0].innerText; // first TD is param_name
        EMLPV.logData.param_value = cells[1].innerText; // second TD is param_value (possibly truncated)
        EMLPV.logData.item_type = 'parameter';

        EMLPV.ajax( 'fetchLogItemValue', 
            EMLPV.logData, 
            EMLPV.fetchLogParameterValueCallback 
        );
    });

    $(document)
        .off('click.emlpv.log-message', 'table#DataTables_Table_0 tr td.message-column')
        .on('click.emlpv.log-message', 'table#DataTables_Table_0 tr td.message-column', function(e) {

        const td = $(this)[0]; // using the DOM element directly in this handler

        const cells = td.closest('tr').querySelectorAll('td'); // get all TDs in the row

        EMLPV.logData = EMLPV.logDataFromRowCells(cells);
        EMLPV.logData.item_type = 'message';

        EMLPV.ajax( 'fetchLogItemValue', 
            EMLPV.logData, 
            EMLPV.fetchLogMessageCallback 
        );
    });
});
