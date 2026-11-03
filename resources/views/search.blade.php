<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel Smart Search</title>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 45px 20px;
            background: #f1f5f9;
            color: #1e293b;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
        }

        h1 {
            margin: 0 0 25px;
            text-align: center;
            font-size: 28px;
            color: #0f172a;
        }

        .search-wrapper {
            position: relative;
        }

        #search {
            width: 100%;
            padding: 15px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: #fff;
            font-size: 15px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        #search:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        #drop {
            display: none;
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            z-index: 100;
            max-height: 280px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .10);
        }

        .suggestion {
            padding: 13px 16px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .suggestion:last-child {
            border-bottom: none;
        }

        .suggestion:hover,
        .suggestion.active {
            background: #eff6ff;
            color: #1d4ed8;
        }

        #info {
            min-height: 22px;
            margin: 13px 0;
            font-size: 13px;
            color: #64748b;
        }

        .table-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-header h2 {
            margin: 0;
            font-size: 16px;
            color: #0f172a;
        }

        #result-count {
            color: #64748b;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 13px 18px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        td {
            padding: 13px 18px;
            border-top: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
            overflow-wrap: anywhere;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .selected-row {
            background: #ecfdf5 !important;
        }

        .selected-row td {
            color: #166534;
            font-weight: 600;
            border-top-color: #bbf7d0;
        }

        .selected-label {
            display: inline-block;
            margin-left: 8px;
            padding: 4px 8px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 11px;
            font-weight: 600;
        }

        .empty-state {
            padding: 30px 15px !important;
            text-align: center;
            color: #94a3b8;
        }

        .pagination-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
            padding: 18px;
            border-top: 1px solid #e2e8f0;
        }

        .page-btn {
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #fff;
            color: #475569;
            font-size: 13px;
            cursor: pointer;
        }

        .page-btn:hover:not(:disabled) {
            border-color: #2563eb;
            color: #2563eb;
        }

        .page-btn.current {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
        }

        .page-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .usage-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 16px;
            padding: 15px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
        }

        .usage-title {
            color: #64748b;
            font-size: 13px;
        }

        #usage {
            color: #0f172a;
            font-size: 18px;
            font-weight: 700;
        }

        @media (max-width: 600px) {
            body {
                padding: 25px 12px;
            }

            h1 {
                font-size: 23px;
            }

            th,
            td {
                padding: 11px 10px;
            }

            .table-header {
                padding: 13px 12px;
            }

            .pagination-wrapper {
                gap: 4px;
                padding: 12px 6px;
            }

            .page-btn {
                min-width: 32px;
                height: 34px;
                padding: 0 7px;
            }
        }
    </style>
</head>

<body>

    <main class="container">

        <h1>Smart Search</h1>

        <div class="search-wrapper">

            <input type="text" id="search" placeholder="Search records..." autocomplete="off"
                aria-label="Search records" aria-controls="drop" aria-expanded="false">

            <div id="drop" role="listbox"></div>

        </div>

        <p id="info" role="status" aria-live="polite"></p>

        <section class="table-card">

            <div class="table-header">

                <h2 id="table-title">Search Results</h2>

                <span id="result-count">0 records</span>

            </div>

            <table>

                <thead>
                    <tr>
                        <th width="90">ID</th>
                        <th>Content</th>
                    </tr>
                </thead>

                <tbody id="rows"></tbody>

            </table>

            <div id="pagination" class="pagination-wrapper"></div>

        </section>

        <div class="usage-card">

            <span class="usage-title">Total API usages</span>

            <span id="usage">{{ $usageCount }}</span>

        </div>

    </main>

    <script>
        // Application state
        let page = 1;

        const visiblePages = 10;

        let debounceTimer = null;
        let searchRequest = null;

        let currentRecords = [];
        let activeIndex = -1;

        let isSelecting = false;
        let selectedRecord = null;

        // Cached DOM elements
        const $search = $('#search');
        const $drop = $('#drop');
        const $rows = $('#rows');
        const $info = $('#info');
        const $usage = $('#usage');
        const $pagination = $('#pagination');

        // Update counter from database-backed API response
        const updateUsage = (value) => {
            const count = Number(value);

            if (Number.isSafeInteger(count) && count >= 0) {
                $usage.text(count);
            }
        };

        // Close autocomplete suggestions
        const closeSuggestions = () => {
            $drop.empty().hide();

            $search.attr('aria-expanded', 'false');

            activeIndex = -1;
        };

        // Render table row
        const renderRow = (record, selected) => {
            const $row = $('<tr>').attr('data-id', record.id);

            if (selected) {
                $row.addClass('selected-row');
            }

            $row.append(
                $('<td>').text(record.id),
                $('<td>').append(
                    $('<span>').text(record.content)
                )
            );

            if (selected) {
                $row.find('td:last').append(
                    $('<span>', {
                        class: 'selected-label'
                    }).text('Selected')
                );
            }

            return $row;
        };

        // Render numbered pagination
        const renderPagination = (hasMore) => {
            $pagination.empty();

            // No records or a selected record: hide pagination.
            if (currentRecords.length === 0 || selectedRecord) {
                $pagination.hide();
                return;
            }

            $pagination.show();

            const startPage =
                Math.floor((page - 1) / visiblePages) * visiblePages + 1;

            const endPage = startPage + visiblePages - 1;

            const $previous = $('<button>', {
                    type: 'button',
                    class: 'page-btn'
                })
                .text('Previous')
                .prop('disabled', page <= 1)
                .on('click', () => {
                    if (page > 1) {
                        page--;
                        load(false);
                    }
                });

            $pagination.append($previous);

            for (
                let pageNumber = startPage; pageNumber <= endPage; pageNumber++
            ) {
                const number = pageNumber;

                const $button = $('<button>', {
                    type: 'button',
                    class: 'page-btn'
                }).text(number);

                if (number === page) {
                    $button.addClass('current');
                }

                $button.on('click', () => {
                    if (number !== page) {
                        page = number;
                        load(false);
                    }
                });

                $pagination.append($button);
            }

            const $next = $('<button>', {
                    type: 'button',
                    class: 'page-btn'
                })
                .text('Next')
                .prop('disabled', !hasMore)
                .on('click', () => {
                    if (hasMore) {
                        page++;
                        load(false);
                    }
                });

            $pagination.append($next);
        };

        // Load records from API
        const load = (showSuggestions) => {
            const query = $.trim($search.val());

            // Minimum search length
            if (query.length > 0 && query.length < 3) {
                if (searchRequest) {
                    searchRequest.abort();
                    searchRequest = null;
                }

                currentRecords = [];

                $rows.empty();

                closeSuggestions();

                $pagination.empty().hide();

                $info.text('Enter at least 3 characters.');

                $('#result-count').text('0 records');

                return;
            }

            // Cancel previous request
            if (searchRequest) {
                searchRequest.abort();
            }

            selectedRecord = null;

            $('#table-title').text('Search Results');

            $info.text('Searching...');

            // API request
            searchRequest = $.ajax({
                url: '/api/search',
                method: 'GET',
                dataType: 'json',
                data: {
                    search: query,
                    page: page
                }
            });

            searchRequest.done((result) => {

                if (result.success !== true) {
                    $info.text('Search failed. Please try again.');
                    return;
                }

                // Update database-backed counter
                updateUsage(result.usage_count);

                currentRecords = result.data || [];

                $rows.empty();

                $drop.empty();

                activeIndex = -1;

                // Empty results
                if (currentRecords.length === 0) {
                    $rows.append(
                        $('<tr>').append(
                            $('<td>', {
                                colspan: 2,
                                class: 'empty-state'
                            }).text('No records found.')
                        )
                    );
                }

                // Render records and suggestions
                $.each(currentRecords, (index, record) => {

                    $rows.append(renderRow(record, false));

                    $('<div>', {
                            class: 'suggestion',
                            role: 'option',
                            'aria-selected': 'false'
                        })
                        .text(record.content)
                        .data('record', record)
                        .on('mousemove', () => {
                            setActiveSuggestion(index);
                        })
                        .appendTo($drop);
                });

                // Show suggestions when typing
                const shouldShow = (
                    showSuggestions &&
                    query.length >= 3 &&
                    currentRecords.length > 0
                );

                $drop.toggle(shouldShow);

                $search.attr(
                    'aria-expanded',
                    shouldShow ? 'true' : 'false'
                );

                // Render pagination
                renderPagination(result.has_more === true);

                // Result count
                $('#result-count').text(
                    currentRecords.length + ' records'
                );

                $info.text(
                    shouldShow ?
                    'Select a suggestion from the dropdown.' :
                    ''
                );
            });

            // Handle API errors
            searchRequest.fail((xhr, status) => {

                if (status === 'abort') {
                    return;
                }

                closeSuggestions();

                $info.text(
                    xhr.responseJSON && xhr.responseJSON.message ?
                    xhr.responseJSON.message :
                    'Search failed. Please try again.'
                );

                console.error(
                    'Search API error:',
                    xhr.responseJSON || xhr.statusText
                );
            });
        };

        // Highlight active suggestion
        const setActiveSuggestion = (index) => {

            activeIndex = index;

            $drop.find('.suggestion').each(function(i) {

                const active = i === activeIndex;

                $(this)
                    .toggleClass('active', active)
                    .attr(
                        'aria-selected',
                        active ? 'true' : 'false'
                    );
            });
        };

        // Select one suggestion
        const selectSuggestion = (record) => {

            if (isSelecting) {
                return;
            }

            isSelecting = true;

            $info.text('Recording selection...');

            $.ajax({
                    url: record.selection_url,
                    method: 'POST',
                    dataType: 'json'
                })
                .done((result) => {

                    if (result.success !== true || result.selected !== true) {
                        $info.text('Selection could not be completed.');
                        return;
                    }

                    // Update counter from the actual database response
                    updateUsage(result.usage_count);

                    selectedRecord = record;

                    // Update input
                    $search.val(record.content);

                    // Show selected record
                    $rows.empty();

                    $rows.append(renderRow(record, true));

                    // Update table heading
                    $('#table-title').text('Selected Record');

                    $('#result-count').text('1 selected record');

                    // Hide pagination
                    $pagination.empty().hide();

                    // Close suggestions
                    closeSuggestions();

                    $info.text(
                        result.recorded === false ?
                        'This record was already counted. Selection successful.' :
                        'Suggestion selected successfully.'
                    );
                })
                .fail((xhr) => {

                    let message = 'Selection could not be recorded.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    $info.text(message);

                    console.error(
                        'Selection API error:',
                        xhr.responseJSON || xhr.statusText
                    );
                })
                .always(() => {
                    isSelecting = false;
                });
        };

        // Search input
        $search.on('input', () => {

            clearTimeout(debounceTimer);

            // Reset pagination for a new search
            page = 1;

            selectedRecord = null;

            debounceTimer = setTimeout(() => {
                load(true);
            }, 250);
        });

        // Keyboard navigation
        $search.on('keydown', (event) => {

            if (event.key === 'Escape') {
                closeSuggestions();
                return;
            }

            if (
                !$drop.is(':visible') ||
                currentRecords.length === 0
            ) {
                return;
            }

            if (event.key === 'ArrowDown') {

                event.preventDefault();

                setActiveSuggestion(
                    (activeIndex + 1) % currentRecords.length
                );

            } else if (event.key === 'ArrowUp') {

                event.preventDefault();

                setActiveSuggestion(
                    (activeIndex - 1 + currentRecords.length) %
                    currentRecords.length
                );

            } else if (
                event.key === 'Enter' &&
                activeIndex >= 0
            ) {

                event.preventDefault();

                selectSuggestion(currentRecords[activeIndex]);
            }
        });

        // Click suggestion
        $drop.on('click', '.suggestion', function() {

            const record = $(this).data('record');

            selectSuggestion(record);
        });

        // Close dropdown on outside click
        $(document).on('click', (event) => {

            if ($(event.target).closest('.search-wrapper').length === 0) {
                closeSuggestions();
            }
        });

        // Initial load
        load(false);
    </script>

</body>

</html>
