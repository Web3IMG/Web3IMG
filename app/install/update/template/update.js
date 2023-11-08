$(function () {
    setInterval(function () {
        var $el = $(".animated-ellipsis");
        var ellipsis = $el.html();
        ellipsis = ellipsis + ".";
        if (ellipsis.length > 3) {
            ellipsis = "";
        }
        $el.html(ellipsis);
    }, 400);
    update.process();
});

var update = {
    vars: {},
    process: function (callback) {
        var _this = this;
        _this.addLog(PF.fn._s("Installed version is v%s", vars.current_version));
        var LICENSE = prompt(PF.fn._s("Enter your license key"), "CHEVERETO_V3_KEY");
        _this.license = LICENSE;
        $.ajax({
            url: vars.url,
            method: 'POST',
            data: {
                auth_token: PF.obj.config.auth_token,
                action: "check-license",
                license: _this.license
            }
        })
            .done(function () {
                _this.addLog(PF.fn._s("Valid license key", _this.vars.target_version));
                _this.ask();
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                _this.abort(_this.readError(jqXHR, textStatus), 100);
                return;
            });
    },
    ask: function (callback) {
        var _this = this;
        _this.addLog(PF.fn._s("Checking for updates"));
        $.ajax({
            url: vars.url,
            data: {
                auth_token: PF.obj.config.auth_token,
                action: "ask"
            }
        })
            .done(function (data) {
                _this.addLog(PF.fn._s("Last available release is v%s", data.software.current_version));
                if (PF.fn.versionCompare(vars.current_version, data.software.current_version) == -1) { // Can update
                    _this.vars.target_version = data.software.current_version;
                    _this.addLog(PF.fn._s("Update needed, proceeding to download"));
                    _this.download(function () {
                        _this.extract(function () {
                            _this.install();
                        });
                    });
                } else {
                    $("h1").html(PF.fn._s("No update needed"));
                    _this.addLog(PF.fn._s("System files already up to date", vars.current_version));
                    _this.install();
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                _this.abort(_this.readError(jqXHR, textStatus), 100);
                return;
            });
    },
    download: function (callback) {
        var _this = this;
        _this.addLog(PF.fn._s("Starting v%s download", this.vars.target_version));
        $.ajax({
            url: vars.url,
            method: 'POST',
            data: {
                auth_token: PF.obj.config.auth_token,
                action: "download",
                version: _this.vars.target_version,
                license: _this.license
            },
        })
            .done(function (data) {
                _this.vars.target_filename = data.download.filename;
                _this.addLog(PF.fn._s("Downloaded v%s, proceeding to extraction", _this.vars.target_version));
                if (typeof callback == "function") {
                    callback();
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                _this.abort(_this.readError(jqXHR, textStatus), 100);
                return;
            });

    },
    extract: function (callback) {
        var _this = this;
        _this.addLog(PF.fn._s("Attempting to extract v%s", this.vars.target_version));
        $.ajax({
            url: vars.url,
            method: 'POST',
            data: {
                auth_token: PF.obj.config.auth_token,
                action: "extract",
                file: _this.vars.target_filename
            },
        })
            .done(function (data) {
                _this.addLog(PF.fn._s("Extraction completed", _this.vars.target_version));
                setTimeout(function () {
                    _this.addLog(PF.fn._s("Proceeding to install the update", _this.vars.target_version));
                    if (typeof callback == "function") {
                        callback();
                    }
                }, 500);
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                _this.abort(_this.readError(jqXHR, textStatus), 100);
                return;
            });
    },
    install: function () {
        var _this = this;
        _this.addLog(PF.fn._s("Redirecting in %s seconds...", '5'));
        setTimeout(function () {
            window.location = PF.obj.config.base_url + "/install";
        }, 5000);
    },
    addLog: function (message, code) {
        if (!code) code = 200;
        var $el = $("ul");
        var d = PF.fn.getDateTime().substring(11);
        var $event = $("<li/>", {
            class: code != 200 ? 'color-red' : null,
            text: d + ' ' + message
        });
        $el.prepend($event);
    },
    abort: function (message) {
        $("h1").html(PF.fn._s("Update failed"));
        if (message) {
            this.addLog(message, 400);
        }
    },
    readError: function (jqXHR, statusText) {
        if ("responseJSON" in jqXHR && "error" in jqXHR.responseJSON && "message" in jqXHR.responseJSON.error) {
            return jqXHR.responseJSON.error.message;
        }
        else {
            return statusText;
        }
    }
};