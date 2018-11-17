class Phollow {
    constructor() {
        this.isWindowActive = false;
        this.errorCount = 0;
        this.newErrorCount = 0;
        this.meta = null;
        this.ws = null;
        this.templateScriptTable = null;
        this.templateScriptDataElement = null;
        this.templateAlert = null;
        this.$scriptContainer = null;
        this.scriptElements = {};
    }

    run(done) {
        try {
            let that = this;
            $.when()
                .then(
                    function () {
                        window.onblur = function () {
                            that.isWindowActive = false;
                        };
                        window.onfocus = function () {
                            that.isWindowActive = true;
                            if (that.errorCount) {
                                Phollow.setHat('green');
                            }
                            that.newErrorCount = 0;
                            Phollow.setTitle(that.newErrorCount);
                        };
                        $('#status').toggleClass('badge-light badge-warning').text('Connecting');
                        that.compileTemplates();
                        that.$scriptContainer = $('#script-container');
                    }
                )
                .then(
                    function () {
                        return $.ajax('data/meta');
                    }
                )
                .then(
                    function (meta) {
                        that.setMeta(meta.data);
                    },
                    function () {
                        throw new Error("Failed fetching meta");
                    }
                )
                .then(
                    function () {
                        return $.ajax('data/documents');
                    }
                )
                .then(
                    function (documents) {
                        let $progressBar = $('#modal-loading-progress-bar');
                        let count = documents['data'].length;
                        for (let i = 0; i < count; ++i) {
                            that.processDocument(documents['data'][i]);
                            $progressBar.css('width', (i * 100 / count) + '%');
                        }
                    },
                    function () {
                        throw new Error("Failed fetching data");
                    }
                )
                .then(
                    function () {
                        that.prepareWs();
                    }
                )
                .then(
                    function () {
                        $(document)
                            .on(
                                'click',
                                '.script-table thead',
                                function () {
                                    let $tbody = $(this).siblings('tbody');
                                    if ($tbody.is(':visible')) {
                                        $tbody.hide();
                                    } else {
                                        $tbody.show();
                                    }
                                }
                            )
                            .on(
                                'click',
                                '.script-table tbody tr',
                                function () {
                                    let error = $(this).data('error');
                                    $('#modal-error-header')
                                        .toggleClass(
                                            function (index, className) {
                                                return className
                                                    .split(' ')
                                                    .filter(className => 'alert-' === className.substr(0, 6))
                                                    .join(' ');
                                            }
                                        )
                                        .addClass('alert-' + error['state']);
                                    $('#modal-error-script-id').text(error['meta']['scriptId']);
                                    $('#modal-error-id').text(error['meta']['id']);
                                    $('#modal-error-timestamp').text(error['data']['timestamp']);
                                    $('#modal-error-severity')
                                        .toggleClass(
                                            function (index, className) {
                                                return className
                                                    .split(' ')
                                                    .filter(className => 'badge-' === className.substr(0, 6))
                                                    .join(' ');
                                            }
                                        )
                                        .addClass('badge-' + error['state'])
                                        .text(error['data']['severityName']);
                                    $('#modal-error-message').text(error['data']['message']);
                                    $('#modal-error-trace').text(error['data']['trace'].join("\n"));
                                    $('#modal-error').modal('show');
                                }
                            );
                        $(function () {
                            $('body').tooltip(
                                {
                                    selector: '[data-toggle="tooltip"]'
                                }
                            );
                        })
                    }
                )
                .then(
                    function () {
                        if (done) {
                            done();
                        }
                        setTimeout(
                            function () {
                                $('#modal-loading').modal('hide');
                            },
                            500
                        );
                    }
                );
        } catch (e) {
            this.pushAlert('danger', 'Initialization exception', e);
        }
    }

    static compileTemplate(id) {
        return Handlebars.compile(document.getElementById(id).innerHTML);
    }

    static setHat(icon) {
        let uri = 'images/hat-' + icon + '.png';
        $('#favicon').attr('href', uri);
    }

    static setTitle(count) {
        $('#title').text((count ? '[' + count + '] ' : '') + 'webgraphe/phollow');
    }

    prepareWs() {
        let server = this.meta['application']['server']['websocket'];
        let uri = 'ws://' + server['host'] + ':' + server['port'];
        this.ws = new WebSocket(uri);
        let that = this;

        this.ws.onopen = function () {
            Phollow.setHat('green');
            Phollow.setTitle(0);
            $('#status').toggleClass('badge-warning badge-success').text('Connected');
        };
        this.ws.onclose = function () {
            that.pushAlert('danger', 'WebSocket Connection', "The connection has been closed by the server");
            that.ws = null;
            Phollow.setTitle(0);
            $('#status').toggleClass('badge-success badge-danger').text('Disconnected');
            $('.header-bar').addClass('alert-danger');
        };
        this.ws.onmessage = function (message) {
            try {
                const document = JSON.parse(message.data);
                if (!that.processDocument(document)) {
                    return;
                }

                if (!that.isWindowActive) {
                    Phollow.setHat('red');
                }
            } catch (exception) {
                console.log("Exception", exception, message.data);
            }
        };
        this.ws.onerror = function (e) {
            that.pushAlert('danger', 'WebSocket Error', "An error occurred with the WebSocket");
            console.log("Error", e);
        };
    }

    openConnection(connectionOpened) {
        const scriptId = connectionOpened['meta']['scriptId'];
        const $scriptTable = $(this.templateScriptTable(connectionOpened));
        this.scriptElements[scriptId] = {
            errorCount: 0,
            tbody: $scriptTable.find('tbody'),
            thead_tr: $scriptTable.find('thead tr'),
            progress: $scriptTable.find('thead tr th i.script-progress'),
            errorCountBadge: $scriptTable.find('thead tr th span.script-error-count'),
            api: $scriptTable.find('.script-api'),
            hostname: $scriptTable.find('.script-hostname'),
            method: $scriptTable.find('.script-method'),
            path: $scriptTable.find('.script-path'),
            time: $scriptTable.find('.script-time'),
            feedback: $scriptTable.find('.script-feedback'),
        };
        this.$scriptContainer.append($scriptTable);

        return true;
    }

    startScript(scriptStarted) {
        const scriptId = scriptStarted['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        scriptElement.thead_tr.toggleClass('bg-warning bg-primary');
        scriptElement.progress.toggleClass('fa-stopwatch fa-spin fa-spinner');
        scriptElement.api.text(scriptStarted['data']['serverApi']);
        scriptElement.hostname.text(scriptStarted['data']['hostname']);
        scriptElement.method.text(scriptStarted['data']['method']);
        scriptElement.path.text(scriptStarted['data']['path']);

        return true;
    }

    addError(error) {
        ++this.errorCount;
        switch (error['data']['severityName']) {
            case 'ERROR':
            case 'PARSE':
            case 'CORE_ERROR':
            case 'COMPILE_ERROR':
            case 'USER_ERROR':
            case 'RECOVERABLE_ERROR':
                error['state'] = 'danger';
                break;
            case 'WARNING':
            case 'CORE_WARNING':
            case 'COMPILE_WARNING':
            case 'USER_WARNING':
                error['state'] = 'warning';
                break;
            case 'NOTICE':
            case 'USER_NOTICE':
                error['state'] = 'info';
                break;
            case 'DEPRECATED':
            case 'USER_DEPRECATED':
                error['state'] = 'secondary';
                break;
            case 'STRICT':
                error['state'] = 'primary';
                break;
            default:
                error['state'] = 'light';
                break;
        }
        const $error = $(this.templateScriptDataElement(error));
        $error.data('error', error);
        const scriptId = error['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        scriptElement.tbody.append($error);
        if (!scriptElement.errorCount) {
            scriptElement.errorCountBadge.toggleClass('badge-secondary badge-warning');
        }
        scriptElement.errorCountBadge.text(++scriptElement.errorCount);

        return true;
    }

    endScript(scriptEnded) {
        const scriptId = scriptEnded['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        scriptElement.thead_tr.toggleClass('bg-primary bg-success');
        scriptElement.progress.toggleClass('fa-spin fa-spinner fa-check-circle');
        scriptElement.time.html('<samp>' + scriptEnded['data']['time'].toFixed(3) + '<samp> <i class="fas fa-fw fa-stopwatch"></i>');
        if (!scriptElement.errorCount) {
            scriptElement.errorCountBadge.toggleClass('badge-secondary badge-success');
        }

        return true;
    }

    closeConnection(connectionClosed) {
        const scriptId = connectionClosed['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        if (scriptElement.thead_tr.hasClass('bg-primary')) {
            scriptElement.thead_tr.toggleClass('bg-primary bg-danger');
            scriptElement.time.html('<i class="fas fa-fw fa-question-circle"></i>');
            scriptElement.feedback.text('Connection closed unexpectedly');
            scriptElement.progress.toggleClass('fa-spin fa-spinner fa-exclamation-triangle');
        } else {
            scriptElement.thead_tr.toggleClass('bg-success bg-secondary');
        }

        return true;
    }

    pushAlert(level, title, body) {
        const $alert = $(
            this.templateAlert(
                {
                    type: level,
                    title: title,
                    body: body,
                    icon: 'exclamation-triangle'
                }
            )
        );
        const hide = function () {
            $alert.remove()
        };
        $alert.find('.delete').on('click', hide);
        $('#alerts').append($alert);
    }

    setMeta(meta) {
        this.meta = meta;
    }

    compileTemplates() {
        this.templateScriptTable = Phollow.compileTemplate('template-script-table');
        this.templateScriptDataElement = Phollow.compileTemplate('template-script-data-element');
        this.templateAlert = Phollow.compileTemplate('template-alert');
    }

    processDocument(document) {
        const type = document.meta.type;
        switch (type) {
            case 'connectionOpened':
                return this.openConnection(document);
            case 'scriptStarted':
                return this.startScript(document);
            case 'error':
                return this.addError(document);
            case 'scriptEnded':
                return this.endScript(document);
            case 'connectionClosed':
                return this.closeConnection(document);
        }

        console.log("Unknown document type", type);

        return false;
    }
}

$(function () {
    let phollow = (new Phollow);
    $('#modal-loading')
        .on(
            'shown.bs.modal',
            function () {
                phollow.run();
            }
        )
        .modal('show');
});
