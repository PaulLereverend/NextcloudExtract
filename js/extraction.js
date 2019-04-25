$(document).ready(function () {

	var actionsExtract = {
		init: function () {
			var self = this;
			OCA.Files.fileActions.registerAction({
				name: 'extractzip',
				displayName: 'Extract here',
				mime: 'application/zip',
				permissions: OC.PERMISSION_UPDATE,
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				iconClass: 'icon-extract',
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
                            external : 0,
                            shareOwner : context.fileList.dirInfo.shareOwnerId 
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

            // RAR
            OCA.Files.fileActions.registerAction({
				name: 'extractrar',
				displayName: 'Extract here',
				mime: 'application/x-rar-compressed',
				permissions: OC.PERMISSION_READ,
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				iconClass: 'icon-extract',
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
                            external : 0,
                            shareOwner : context.fileList.dirInfo.shareOwnerId 
                        };
                    }
                    var tr = context.fileList.findFileEl(filename);
				    context.fileList.showFileBusyState(tr, true);
                    $.ajax({
                        type: "POST",
                        async: "false",
                        url: OC.filePath('extract', 'ajax','extractRar.php'),
                        data: data,
                        success: function() {
                            context.fileList.reload();
                        }
                    });
				}
            }); 
            // TAR
            //'application/x-tar', 'application/x-7z-compressed'
            var types = ['application/x-tar', 'application/x-7z-compressed', 'application/x-bzip2', 'application/x-deb', 'application/x-gzip'];
            types.forEach(type => {
                OCA.Files.fileActions.registerAction({
                    name: 'extractOthers',
                    displayName: 'Extract here',
                    mime: type,
                    permissions: OC.PERMISSION_READ,
                    type: OCA.Files.FileActions.TYPE_DROPDOWN,
                    iconClass: 'icon-extract',
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
                                external : 0,
                                shareOwner : context.fileList.dirInfo.shareOwnerId 
                            };
                        }
                        var tr = context.fileList.findFileEl(filename);
                        context.fileList.showFileBusyState(tr, true);
                        $.ajax({
                            type: "POST",
                            async: "false",
                            url: OC.filePath('extract', 'ajax','extractOthers.php'),
                            data: data,
                            success: function() {
                                context.fileList.reload();
                            }
                        });
                    }
                }); 
            });
            
            // BZ2
           /* OCA.Files.fileActions.registerAction({
				name: 'extractrar',
				displayName: 'Extract here',
				mime: 'application/x-bzip2',
				permissions: OC.PERMISSION_READ,
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				iconClass: 'icon-extract',
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
                    var tr = context.fileList.findFileEl(filename);
				    context.fileList.showFileBusyState(tr, true);
                    $.ajax({
                        type: "POST",
                        async: "false",
                        url: OC.filePath('extract', 'ajax','extractTar.php'),
                        data: data,
                        success: function() {
                            context.fileList.reload();
                        }
                    });
				}
            });*/
		},
	}
	actionsExtract.init();
});

