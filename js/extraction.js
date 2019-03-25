$(document).ready(function () {

	var actionsExtract = {
		init: function () {
			var self = this;
			OCA.Files.fileActions.registerAction({
				name: 'extractzip',
				displayName: 'Extract here',
				mime: 'application/zip',
				permissions: OC.PERMISSION_READ,
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				iconClass: 'iconextract',
				actionHandler: function (filename, context) {
                    if (context.fileInfoModel.attributes.mountType == "external"){
                        var data = {
                            nameOfFile: filename,
                            directory: '/'+context.dir.split('/').slice(2).join('/'),
                            external : 1
                        };
                    }else{
                        var data = {
                            nameOfFile: filename,
                            directory: context.dir,
                            external : 0
                        };
                    }
                    $.ajax({
                        type: "POST",
                        async: "false",
                        url: OC.filePath('extract', 'ajax','extractHere.php'),
                        data: data,
                        success: function() {
                            context.fileList.reload();
                        }
                    });
				}
            });

            // For the next version
            
            /*OCA.Files.fileActions.registerAction({
				name: 'extractrar',
				displayName: 'Extract here',
				mime: 'application/x-rar-compressed',
				permissions: OC.PERMISSION_READ,
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				iconClass: 'iconextract',
				actionHandler: function (filename, context) {
                    if (context.fileInfoModel.attributes.mountType == "external"){
                        var data = {
                            nameOfFile: filename,
                            directory: '/'+context.dir.split('/').slice(2).join('/'),
                            external : 1
                        };
                    }else{
                        var data = {
                            nameOfFile: filename,
                            directory: context.dir,
                            external : 0
                        };
                    }
                    $.ajax({
                        type: "POST",
                        async: "false",
                        url: OC.filePath('extract', 'ajax','extractHere.php'),
                        data: data,
                        success: function() {
                            context.fileList.reload();
                        }
                    });
				}
            }); */
		},
	}
	actionsExtract.init();
});

