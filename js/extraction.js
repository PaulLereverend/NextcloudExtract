$(document).ready(function () {

    var actionsExtract = {
        createDialog: function (title, init, callback) {
            return OC.dialogs.confirmHtml(
                '',
                title,
                callback,
                true
            ).then(() => {
                var $dialog = $('.oc-dialog:visible');
                var $content = $('.oc-dialog-content');
                var $buttons = $dialog.find('button');

                var $cancelButton = $buttons.eq(0);
                var $confirmButton = $buttons.eq(1);

                $content.empty();

                $cancelButton.text(t('core', 'Cancel'));
                $confirmButton.text(t('core', 'Confirm'));

                init($dialog, $content, $cancelButton, $confirmButton);
            });
        },

        extractDialog: function (filename, context, type) {
            var self = this;

            var dirName = filename;
            var matches = dirName.match('^([^\\.]+)');
            if (matches) {
                dirName = matches[0];
            }

            var data = {
                sourcePath: context.dir ? context.dir + '/' + filename : filename,
                targetDirName: dirName,
                type: type
            };

            var tr = context.fileList.findFileEl(filename);
            context.fileList.showFileBusyState(tr, true);

            var $input = $('<input/>');
            self.createDialog(
                t('extract', 'Extract'),
                ($dialog, $content, _$cancelButton, $confirmButton) => {
                    $dialog.css("min-width", "300px");

                    $confirmButton.text(t('extract', 'Extract'));

                    var $text = $('<p/>');
                    $text.text(t('extract', 'Files will be extracted to this folder:'));
                    $content.append($text);

                    $input.attr('type', 'text').attr('id', 'file-name-input').attr('placeholder', t('extract', 'File Name')).attr('value', dirName);
                    $content.append($input);

                    $input.on('input', () => {
                        if($input.val().trim() === '') {
                            $confirmButton.prop("disabled", true);
                        } else {
                            $confirmButton.prop("disabled", false);
                        }
                    });
                },
                (result) => {
                    context.fileList.showFileBusyState(tr, false);
                    data.targetDirName = $input.val();
                    if (result) {
                        $.ajax({
                            type: "POST",
                            async: "false",
                            url: OC.filePath('extract', 'ajax', 'extract.php'),
                            data: data,
                            success: function (response) {
                                console.log(response);
                                if (response.code === 1) {
                                    context.fileList.reload();
                                } else {
                                    context.fileList.showFileBusyState(tr, false);
                                    OC.dialogs.alert(
                                        t('extract', response.desc),
                                        t('extract', 'Error extracting ' ) + filename
                                    );
                                }
                            }
                        });
                    }
                },
            );
        },

        init: function () {
            var self = this;

            // ZIP
            OCA.Files.fileActions.registerAction({
                name: 'extractzip',
                displayName: t('extract', 'Extract'),
                mime: 'application/zip',
                permissions: OC.PERMISSION_UPDATE,
                type: OCA.Files.FileActions.TYPE_DROPDOWN,
                iconClass: 'icon-extract',
                actionHandler: function (filename, context) {
                    self.extractDialog(filename, context, 'zip');
                }
            });

            // RAR
            OCA.Files.fileActions.registerAction({
                name: 'extractrar',
                displayName: t('extract', 'Extract'),
                mime: 'application/x-rar-compressed',
                permissions: OC.PERMISSION_UPDATE,
                type: OCA.Files.FileActions.TYPE_DROPDOWN,
                iconClass: 'icon-extract',
                actionHandler: function (filename, context) {
                    self.extractDialog(filename, context, 'rar');
                }
            });

            // TAR
            var types = ['application/x-tar', 'application/x-7z-compressed', 'application/x-bzip2', 'application/x-deb', 'application/x-gzip'];
            types.forEach(type => {
                OCA.Files.fileActions.registerAction({
                    name: 'extractOthers',
                    displayName: t('extract', 'Extract'),
                    mime: type,
                    permissions: OC.PERMISSION_UPDATE,
                    type: OCA.Files.FileActions.TYPE_DROPDOWN,
                    iconClass: 'icon-extract',
                    actionHandler: function (filename, context) {
                        self.extractDialog(filename, context, 'other');
                    }
                });
            });
        },
    }
    actionsExtract.init();
});

