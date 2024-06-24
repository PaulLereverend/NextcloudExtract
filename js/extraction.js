$(() => {
    const actionsExtract = {
        createDialog: function (title, init, callback) {
            return OC.dialogs.confirmHtml('', title, callback, true)
                .then(() => {
                    const $dialog = $('.oc-dialog:visible');
                    const $content = $('.oc-dialog-content');
                    const $buttons = $('.oc-dialog-buttonrow').find('button');
                    const $cancelButton = $buttons.eq(0);
                    const $confirmButton = $buttons.eq(1);

                    $content.empty();

                    $cancelButton.text(t('core', 'Cancel'));
                    $confirmButton.text(t('core', 'Confirm'));

                    init($dialog, $content, $cancelButton, $confirmButton);
                });
        },

        extractDialog: function (filename, context, type) {
            const self = this;
            let dirName = filename.match('^([^\\.]+)')[0] || filename;

            const data = {
                sourcePath: context.dir ? `${context.dir}/${filename}` : filename, targetDirName: dirName, type: type,
            };

            const tr = context.fileList.findFileEl(filename);
            context.fileList.showFileBusyState(tr, true);

            const $input = $('<input/>').css('width', '100%');

            self.createDialog(t('extract', 'Extract'), ($dialog, $content, _$cancelButton, $confirmButton) => {
                $dialog.css({
                    'min-width': '300px', width: '50%', 'max-width': '600px',
                });

                $confirmButton.text(t('extract', 'Extract'));

                const $text = $('<p/>').text(t('extract', 'Files will be extracted to this folder:'));
                $content.append($text);

                $input.attr('type', 'text').attr('id', 'file-name-input').attr('placeholder', t('extract', 'File Name')).attr('value', dirName);
                $content.append($input);

                $input.on('input', () => {
                    $confirmButton.prop('disabled', $input.val().trim() === '');
                });
            }, (result) => {
                context.fileList.showFileBusyState(tr, false);
                data.targetDirName = $input.val();
                if (result) {
                    $.ajax({
                        type: 'POST',
                        async: 'false',
                        url: OC.filePath('extract', 'ajax', 'extract.php'),
                        data: data,
                        success: function (response) {
                            console.log(response);
                            if (response.code === 1) {
                                context.fileList.reload();
                            } else {
                                context.fileList.showFileBusyState(tr, false);
                                OC.dialogs.alert(t('extract', response.desc), t('extract', 'Error extracting ') + filename);
                            }
                        },
                    });
                }
            },);
        },

        init: function () {
            const self = this;

            const registerAction = function (name, mime) {
                OCA.Files.fileActions.registerAction({
                    name: name,
                    displayName: t('extract', 'Extract'),
                    mime: mime,
                    permissions: OC.PERMISSION_UPDATE,
                    type: OCA.Files.FileActions.TYPE_DROPDOWN,
                    iconClass: 'icon-extract',
                    actionHandler: function (filename, context) {
                        self.extractDialog(filename, context, mime);
                    },
                });
            };

            // ZIP
            registerAction('extractzip', 'application/zip');

            // RAR
            registerAction('extractrar', 'application/x-rar-compressed');

            // TAR and others
            const types = ['application/x-tar', 'application/x-7z-compressed', 'application/x-bzip2', 'application/x-deb', 'application/x-gzip', 'application/x-compressed'];
            types.forEach(type => {
                registerAction('extractOthers', type);
            });
        },
    };

    actionsExtract.init();
});
