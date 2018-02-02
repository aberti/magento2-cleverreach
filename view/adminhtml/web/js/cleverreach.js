(function () {

    var isConnected = false,
        initialAdjustment = true;

    /**
     * Configurations and constants
     *
     * @type {{get}}
     */
    var CONFIG = (function () {
        var constants = {
            SAVE_CONFIGURATION_URL: "/cleverreach/configuration/save?form_key=" + window.FORM_KEY,
            GET_CONFIGURATION_URL: "/cleverreach/configuration/get?form_key=" + window.FORM_KEY,
            VALIDATION_URL: "/cleverreach/configuration/validate?form_key=" + window.FORM_KEY,
            IMPORT_START_URL: "/cleverreach/import/start?form_key=" + window.FORM_KEY,
            CHECK_PROGRESS_URL: "/cleverreach/import/checkprogress?form_key=" + window.FORM_KEY,
            IMPORT_LOCKED: 0,
            NOTHING_TO_IMPORT: 1,
            IMPORT_STARTED: 2,
            INCORRECT_BATCH: 3,
            CONFIGURATION_SET: 4,
            CONFIGURATION_RESET: 5,
            SUCCESSFUL_CONNECTION: 6,
            UNSUCCESSFUL_CONNECTION: 7,
            HAD_SUCCESSFUL_CONNECTION: 9
        };

        return {
            get: function (name) {
                return constants[name];
            }
        };
    })();

    var handlers = {
        load: function (selector, time) {
            if (document.querySelector(selector) !== null) {
                ajax.post(handlers.getBaseUrl() + CONFIG.get('GET_CONFIGURATION_URL'), null, handlers.pageLoadResponseHandler, 'json');
                tabs.initTabs();
                handlers.initHandlers();
                return true;
            }

            setTimeout(function () {
                handlers.load(selector, time);
            }, time);
        },
        /**
         * Initializes handlers for various buttons and attaches event listeners for clicks to them
         */
        initHandlers: function () {
            var connect = document.getElementById('connect'),
                reset = document.getElementById('reset'),
                save = document.getElementById('save'),
                start = document.getElementById('start'),
                saveLoader = document.getElementById('save-loader'),
                confirm = new confirmation();

            if (connect) {
                connect.addEventListener('click', function () {
                    handlers.checkConnectionStatus();
                });
            }

            if (save) {
                save.addEventListener('click', function () {
                    if (handlers.store()) {
                        saveLoader.style.display = 'block';
                    }
                });
            }

            if (reset) {
                reset.addEventListener('click', function () {
                    confirm.render();
                });
            }

            if (start) {
                start.addEventListener('click', function () {
                    handlers.startImport(this);
                });
            }
        },

        /**
         * A function for storing all values on all the tabs in the database
         */
        store: function () {
            var i, batch = document.getElementById('batch'),
                mappings = document.getElementsByName('group_ids'),
                groupMappings = {},
                params = [];

            if (batch.value === '' || isNaN(batch.value) || batch.value < 50 || batch.value > 250) {
                handlers.showMessage(document.getElementById('batch_size_message').value);

                return false;
            }

            params.debugMode = document.getElementById('debug').value;
            params.productSearch = document.getElementById('search').value;

            for (i = 0; i < mappings.length; i++) {
                groupMappings[mappings[i].value] = {
                    systemGroup: mappings[i].value,
                    crGroup: document.getElementById('cr_' + mappings[i].value).value,
                    optInForm: document.getElementById('form_' + mappings[i].value).value
                };
            }

            params.groupMappings = JSON.stringify(groupMappings);
            params.batchSize = batch.value;

            ajax.post(handlers.getBaseUrl() + CONFIG.get('SAVE_CONFIGURATION_URL'), params, handlers.configurationResponseHandler, 'json', true);

            return true;
        },

        /**
         * Handles response on page load
         *
         * @param response
         */
        pageLoadResponseHandler: function (response) {
            // hide initial loader ans show content
            document.querySelector('.cr-loader').style.display = 'none';
            document.querySelector('.cr-content').style.display = 'block';

            if (!response.connected) {
                document.querySelector('.cr-disconnected').style.display = 'inline-block';
                return;
            }

            document.querySelector('.cr-connected').style.display = 'inline-block';

            var configurations = response.configurations,
                productSearch = document.getElementById('search'),
                debugMode = document.getElementById('debug'),
                batchSize = document.getElementById('batch'),
                mapping,
                selectCrGroup,
                selectOptInForm;

            debugMode.value = configurations.debugMode ? 1 : 0;
            batchSize.value = configurations.batchSize;
            handlers.populateGroups(configurations.groups, document.getElementsByName('groups'));

            for (var key in configurations.mappings) {
                if (configurations.mappings.hasOwnProperty(key)) {
                    mapping = configurations.mappings[key];
                    selectCrGroup = document.getElementById('cr_' + mapping.systemGroup);
                    selectOptInForm = document.getElementById('form_' + mapping.systemGroup);

                    selectCrGroup.addEventListener('change', function () {
                        handlers.populateFormSelect(this, configurations.groups);
                    });

                    selectCrGroup.value = mapping.crGroup;
                    selectCrGroup.dispatchEvent(new Event('change'));
                    selectOptInForm.value = mapping.optInForm;
                }
            }

            initialAdjustment = false;
            if (response.running) {
                progressBar.move(false, response.width);
            }

            productSearch.value = configurations.productSearch ? 1 : 0;
            debugMode.value = configurations.debugMode ? 1 : 0;
            batchSize.value = configurations.batchSize;
        },

        /**
         * Handles response from server regarding setting configuration and importing customers
         *
         * @param response
         * @param status
         */
        configurationResponseHandler: function (response, status) {
            var errorMsgBlock = document.getElementById('error-message-block'),
                saveLoader = document.getElementById('save-loader');

            if (response.status == CONFIG.get('INCORRECT_BATCH')) {
                handlers.showMessage(response.message, false);
            } else {
                errorMsgBlock.style.display = 'none';

                if (initialAdjustment) {
                    handlers.startImport(document.getElementById('next'));
                } else {
                    handlers.showMessage(response.message, true);
                    saveLoader.style.display = 'none';
                }
            }
        },

        /**
         * handles import response
         *
         * @param response
         * @param status
         */
        importResponseHandler: function (response, status) {
            var start = document.getElementById('start'),
                loader = initialAdjustment ? document.getElementById('wizard-import-loader') : document.getElementById('import-loader'),
                next = document.getElementById('next');

            loader.style.display = 'none';

            if (response.status == CONFIG.get('HAD_SUCCESSFUL_CONNECTION')) {
                location.reload();
            } else if (response.status == CONFIG.get('IMPORT_LOCKED')) {
                handlers.showMessage(response.message, false);
            } else if (response.status == CONFIG.get('NOTHING_TO_IMPORT')) {
                handlers.showMessage(response.message, true);
            } else {
                handlers.showMessage(response.message, true);
                progressBar.move(true, 0);
            }

            if (start) {
                start.disabled = false;
            } else {
                next.disabled = false;
            }
        },

        /**
         * Handles response from server regarding connection
         *
         * @param response
         * @param status
         */
        connectionResponseHandler: function (response, status) {
            document.getElementById('connect-loader').style.display = 'none';

            handlers.showMessage(response.message, response.status == CONFIG.get('SUCCESSFUL_CONNECTION'));
        },

        /**
         * Shows message, success or error, depend on second param
         *
         * @param message
         * @param success
         */
        showMessage: function (message, success) {
            var selector = success ? 'success-message' : 'error-message';

            document.getElementById(selector).innerHTML = message;
            document.getElementById('error-message-block').style.display = success ? 'none' : 'block';
            document.getElementById('success-message-block').style.display = success ? 'block' : 'none';
        },
        /**
         * Hides all messages
         */
        hideMessage: function () {
            if (!document.getElementById('error-message-block').style.display) {
                document.getElementById('error-message-block').style.display = 'none';
            }
            document.getElementById('success-message-block').style.display = 'none';
        },
        /**
         * Gets first child
         *
         * @param el
         * @returns {*}
         */
        getFirstChild: function (el) {
            var firstChild = el.firstChild;

            while (firstChild !== null && firstChild.nodeType == 3) { // skip TextNodes
                firstChild = firstChild.nextSibling;
            }

            return firstChild;
        },

        /**
         * Starts import of customers to CleverReach
         */
        startImport: function (button) {
            var importLoader = handlers.getFirstChild(button);

            importLoader.style.display = 'block';
            button.disabled = true;

            ajax.post(handlers.getBaseUrl() + CONFIG.get('IMPORT_START_URL'), null, handlers.importResponseHandler, 'json', true);
        },

        /**
         * Checks connection status
         */
        checkConnectionStatus: function () {
            var disconnected = document.querySelector('.cr-disconnected'),
                connecting = document.querySelector('.cr-connecting'),
                authUrl = document.getElementById('authorize_url'),
                authWin = window.open(authUrl.value, 'authWindow', 'toolbar=0,location=0,menubar=0,width=600');

            var winClosed = setInterval(function () {
                if (authWin.closed) {
                    disconnected.style.display = 'none';
                    connecting.style.display = 'inline-block';
                    clearInterval(winClosed);
                    status();
                }

            }, 250);

            function status()
            {
                ajax.post(handlers.getBaseUrl() + CONFIG.get('VALIDATION_URL'), null, handlers.auth, 'json', true);
            }
        },

        /**
         * Callback function for AJAX request that does authentication
         *
         * @param response
         * @param status
         */
        auth: function (response, status) {
            var next = document.getElementById('next'),
                connected = document.querySelector('.cr-connected'),
                disconnected = document.querySelector('.cr-disconnected'),
                connecting = document.querySelector('.cr-connecting'),
                connect = document.getElementById('connect');

            connecting.style.display = 'none';

            switch (response.status) {
                case CONFIG.get('HAD_SUCCESSFUL_CONNECTION'):
                    location.reload();
                    break;
                case CONFIG.get('SUCCESSFUL_CONNECTION'):
                    handlers.connectionResponseHandler(response, status);
                    connected.style.display = 'inline-block';
                    disconnected.style.display = 'none';
                    connect.style.display = 'none';
                    handlers.populateGroups(response.groups, document.getElementsByName('groups'));

                    isConnected = true;

                    if (next) {
                        next.classList.remove('disabled');
                        next.onclick = function () {
                            handlers.navigate(1);
                        };
                    }
                    break;
                case CONFIG.get('UNSUCCESSFUL_CONNECTION'):
                    disconnected.style.display = 'inline-block';
                    break;
            }
        },

        /**
         * Populates mapping table with CleverReach groups
         *
         * @param groups
         * @param groupSelects
         */
        populateGroups: function (groups, groupSelects) {

            [].forEach.call(groupSelects, function (select) {
                // remove old children
                while (select.hasChildNodes()) {
                    select.removeChild(select.lastChild);
                }

                //adding "none" option
                select.appendChild(handlers.createOption({
                    id: 0,
                    name: document.getElementById('none_label').value
                }));

                groups.forEach(function (group) {
                    select.appendChild(handlers.createOption(group));
                });

                handlers.populateFormSelect(select, groups);

                select.addEventListener('change', function () {
                    handlers.populateFormSelect(select, groups);
                });
            });
        },

        /**
         * Populates mapping table with groups
         *
         * @param select
         * @param groups
         */
        populateFormSelect: function (select, groups) {
            var selectIdParts = select.id.split('_'),
                groupId = selectIdParts[selectIdParts.length - 1],
                formSelect = document.getElementById('form_' + groupId),
                selectedOptionValue = select.options[select.selectedIndex].value,
                selectedGroup,
                i;

            while (formSelect.lastChild) {
                formSelect.removeChild(formSelect.lastChild);
            }

            for (i = 0; i < groups.length; i++) {
                if (groups[i].id == selectedOptionValue) {
                    selectedGroup = groups[i];
                    break;
                }
            }

            handlers.appendOptionsToFormSelect(selectedGroup ? selectedGroup.forms : false, formSelect);
        },

        /**
         * Appends forms as options to the given form select element
         *
         * @param forms
         * @param select
         */
        appendOptionsToFormSelect: function (forms, select) {
            // adding "none" option
            select.appendChild(handlers.createOption({
                id: 0,
                name: document.getElementById('none_label').value
            }));

            if (forms) {
                forms.forEach(function (form) {
                    select.appendChild(handlers.createOption(form));
                });
            }
        },

        /**
         * Function for navigating through the tabs
         *
         * @param page
         */
        navigate: function (page) {
            handlers.hideMessage();

            switch (page) {
                // Configurations
                case 0:
                    tabs.openTab('configurations', page);
                    break;
                // Mappings
                case 1:
                    tabs.openTab('mappings', page);
                    break;
                // Import
                case 2:
                    tabs.openTab('import', page);
                    break;
            }

            handlers.setWizardButtons(page);
        },

        /**
         * Sets actions for previous and next navigation buttons in configuration wizard
         *
         * @param page
         */
        setWizardButtons: function (page) {
            var createSpan = function () {
                    var span = document.createElement('span');

                    span.className = 'loader';
                    span.id = 'wizard-import-loader';
                    span.style.display = 'none';

                    return span;
                },
                next = document.getElementById('next'),
                prev = document.getElementById('prev'),
                tabLinks = document.getElementsByClassName('tab-links');

            if (next && prev) {
                next.style.display = 'block';

                switch (page) {
                    // Configurations
                    case 0:
                        prev.style.display = 'none';

                        if (document.getElementById('wizard-import-loader')) {
                            next.removeChild(createSpan());
                        }

                        if (isConnected) {
                            next.classList.remove('disabled');
                        } else {
                            next.classList.add('disabled');
                        }

                        next.innerHTML = document.getElementById('next_label').value;
                        next.onclick = function () {
                            handlers.navigate(1);
                        };

                        break;
                    // Mappings
                    case 1:
                        document.getElementsByClassName('tab-links')[0].classList.add('complete');

                        prev.style.display = 'block';
                        prev.classList.remove('disabled');
                        prev.onclick = function () {
                            handlers.navigate(0);
                        };

                        next.innerHTML = document.getElementById('next_label').value;
                        next.classList.remove('disabled');
                        next.onclick = function () {
                            handlers.navigate(2);
                        };

                        if (document.getElementById('wizard-import-loader')) {
                            next.removeChild(createSpan());
                        }

                        break;
                    // Import
                    case 2:
                        document.getElementsByClassName('tab-links')[1].classList.add('complete');

                        prev.style.display = 'block';
                        prev.onclick = function () {
                            handlers.navigate(1);
                        };

                        next.innerHTML = document.getElementById('start_import_label').value;
                        next.insertBefore(createSpan(), next.firstChild);

                        next.onclick = function () {
                            if (handlers.store()) {
                                document.getElementById('wizard-import-loader').style.display = 'block';
                            }
                        };

                        break;
                }
            }

            tabLinks[page].classList.remove('disabled');
        },

        /**
         * Gets shop base url
         *
         * @returns {*}
         */
        getBaseUrl: function () {
            return document.getElementById('base_url').value;
        },

        /**
         * Creates drop down option
         *
         * @param form
         * @returns {Element}
         */
        createOption: function (form) {
            var option = document.createElement('option');

            option.value = form.id;
            option.innerHTML = form.name;

            return option;
        }
    };

    var tabs = {
        /**
         * Initializes tabs and attaches corresponding event listeners for clicks
         */
        initTabs: function () {
            var i,
                current,
                tabLinks = document.querySelectorAll('.tab-links');

            for (i = 0; i < tabLinks.length; i++) {
                current = tabLinks[i];

                if (i === 0) {
                    handlers.navigate(0);
                }

                tabs.handleElement(current, i);
            }
        },

        /**
         * Function for setting event handlers for a specific element. We need this function because these events are assigned in a for loop
         * and it's not possible to pass certain parameters, such as iterator variable directly in the function for initializing tabs, so
         * we need this helper function
         *
         * @param element
         * @param i
         */
        handleElement: function (element, i) {
            element.addEventListener('click', function () {
                handlers.navigate(i);
            });
        },

        /**
         * Function for opening a given tab, and closing all the others, so that only the selected tab remains in focus
         *
         * @param tabName
         * @param page
         */
        openTab: function (tabName, page) {
            // Declare all variables
            var i, tabContainers, tabLinks;

            // Get all elements with class="tab-content" and hide them
            tabContainers = document.getElementsByClassName('cr-container');
            for (i = 0; i < tabContainers.length; i++) {
                tabContainers[i].classList.remove('active');
            }

            // Get all elements with class="tablinks" and remove the class "active"
            tabLinks = document.getElementsByClassName('tab-links');
            for (i = 0; i < tabLinks.length; i++) {
                tabLinks[i].classList.remove('active');
            }

            // Show the current tab, and add an "active" class to the link that opened the tab
            tabContainers[page].classList.add('active');
            tabLinks[page].classList.add('active');
        }
    };

    var progressBar = {
        /**
         * Moves progress bar slider from 0 to 100%
         */
        move: function (first, width) {

            var bar = document.getElementById('bar'),
                text = document.getElementById('text'),
                startButton = document.getElementById('start'),
                id = setInterval(frame, 3000);

            text.innerHTML = width + '%';
            bar.style.width = width + '%';

            if (first) {
                document.getElementsByClassName('tab-links')[2].classList.add('complete');
            }

            function frame()
            {
                if (width >= 100) {
                    clearInterval(id);
                    handlers.showMessage(document.getElementById('customers_imported_message').value, true);

                    if (startButton) {
                        startButton.style.display = 'block';
                    }
                } else {
                    ajax.post(handlers.getBaseUrl() + CONFIG.get('CHECK_PROGRESS_URL'), null, function (response, status) {
                        width = response.status;
                        text.innerHTML = width + '%';
                        bar.style.width = width + '%';
                    }, 'json', true);
                }
            }
        }
    };

    /**
     * This is a class for custom confirm box that is being used
     * instead of a default one that is supported by JavaScript
     *
     * @constructor
     */
    function confirmation()
    {

        /**
         * Renders a custom confirm box with corresponding buttons and a message
         *
         * @param dialog
         */
        this.render = function (dialog) {
            var winW = window.innerWidth,
                winH = window.innerHeight,
                dialogOverlay = document.getElementById('dialog-overlay'),
                dialogBox = document.getElementById('dialog-box');

            dialogOverlay.style.display = 'block';
            dialogOverlay.style.height = winH + 'px';
            dialogBox.style.left = (winW / 2) - (550 * 0.5) + 'px';
            dialogBox.style.top = '100px';
            dialogBox.style.display = 'block';

            document.getElementById('dialog-box-foot').innerHTML =
                '<button id="confirm-yes">' + document.getElementById('yes').value + '</button> ' +
                '<button id="confirm-no">' + document.getElementById('no').value + '</button>';

            confirms.initConfirms();
        };

        /**
         * Logic that happens when user confirms his action
         */
        this.yes = function () {
            window.location = document.getElementById('reset_url').value;
        };

        /**
         * Closes the confirm box if user cancels his action
         */
        this.no = function () {
            document.getElementById('dialog-box').style.display = 'none';
            document.getElementById('dialog-overlay').style.display = 'none';
        };

        var confirms = {
            /**
             * Initializes confirm buttons when the confirm box is loaded
             */
            initConfirms: function () {
                var yes = document.getElementById('confirm-yes'),
                    no = document.getElementById('confirm-no'),
                    confirm = new confirmation();

                yes.addEventListener('click', function () {
                    confirm.yes();
                });

                no.addEventListener('click', function () {
                    confirm.no();
                });
            }
        };
    }

    var ajax = {
        x: function () {
            var versions = [
                    'MSXML2.XmlHttp.6.0',
                    'MSXML2.XmlHttp.5.0',
                    'MSXML2.XmlHttp.4.0',
                    'MSXML2.XmlHttp.3.0',
                    'MSXML2.XmlHttp.2.0',
                    'Microsoft.XmlHttp'
                ],
                xhr, i;

            if (typeof XMLHttpRequest !== 'undefined') {
                return new XMLHttpRequest();
            }

            for (i = 0; i < versions.length; i++) {
                try {
                    xhr = new ActiveXObject(versions[i]);
                    break;
                } catch (e) {
                }
            }
            return xhr;
        },
        send: function (url, callback, method, data, format, async) {
            var x = ajax.x();

            if (async === undefined) {
                async = true;
            }

            x.open(method, url, async);
            x.onreadystatechange = function () {
                if (x.readyState == 4) {
                    var response = x.responseText,
                        status = x.status;

                    try {
                        if (format === 'json') {
                            response = JSON.parse(response);
                        }

                        callback(response, status);
                    } catch (err) {
                        location.reload();
                    }
                }
            };

            if (method == 'POST') {
                x.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            }

            x.send(data);
        },
        post: function (url, data, callback, format, async) {
            var query = [];
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    query.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
                }
            }

            ajax.send(url, callback, 'POST', query.join('&'), format, async);
        }
    };

    handlers.load('#base_url', 100);
})();
