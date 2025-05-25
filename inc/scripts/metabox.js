var WP_Attachments = (function($) {
    "use strict";
    
    var attachments = {
        mediaFrame: null, // <-- Add this line

        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initSortable();
            this.initializeFilePreview();
        },
        
        cacheElements: function() {
            this.$container = $('#wpa-attachment-list');
            this.$attachmentItems = $('.wpa-attachment-item');
            this.$addMediaButton = $('.wpa_attach_file');
            this.$previewModal = $('#wpa-preview-modal');
            this.$previewContent = $('#wpa-preview-file');
            this.$previewTitle = $('#wpa-preview-title');
            // No edit modal in new version
            
            // Cache localized variables
            this.editMediaTitle = WP_Attachments_Vars.editMedia || 'Edit Media';
            this.youSureText = WP_Attachments_Vars.youSure || 'Are you sure you want to do this?';
            this.postID = WP_Attachments_Vars.postID || 0;
            this.ajaxurl = WP_Attachments_Vars.ajaxurl || '';
            this.nonce = WP_Attachments_Vars.nonce || '';
        },
        
        bindEvents: function() {
            var self = this;
            
            // Add media button click
            $(document).on('click', '.wpa_attach_file', this.handleAddMedia.bind(this));
            
            // Preview modal close buttons
            $(document).on('click', '.wpa-preview-close', this.closePreviewModal.bind(this));
            
            // Click outside preview modal to close
            $(document).on('click', '.wpa-preview-modal', function(e) {
                if (e.target === this) {
                    self.closePreviewModal();
                }
            });
            
            // Keyboard events for modals
            $(document).on('keydown', this.handleKeyEvents.bind(this));
            
            // Unattach and delete confirmations
            $(document).on('click', 'a[href*="unattach"], a[href*="delete"]', function(e) {
                var isDelete = $(this).attr('href').indexOf('delete') !== -1;
                var message = isDelete ? 
                    'Are you sure you want to delete this permanently?' : 
                    self.youSureText;
                    
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Handle attachment reordering on sortable stop
            this.$container.on('sortstop', this.handleReorder.bind(this));
        },
        
        initSortable: function() {
            if (this.$container.length) {
                this.$container.sortable({
                    items: '.wpa-attachment-item',
                    handle: '.wpa-attachment-drag-handle',
                    cursor: 'move',
                    opacity: 0.7,
                    placeholder: 'wpa-attachment-item ui-sortable-placeholder',
                    forcePlaceholderSize: true,
                    tolerance: 'pointer',
                    containment: 'parent',
                    start: function(e, ui) {
                        ui.item.addClass('ui-sortable-helper');
                        ui.placeholder.height(ui.item.height());
                    },
                    stop: function(e, ui) {
                        ui.item.removeClass('ui-sortable-helper');
                        // Trigger reorder after sorting
                        $(this).trigger('sortstop');
                    },
                    change: function(e, ui) {
                        // Visual feedback during drag
                        ui.placeholder.addClass('ui-sortable-placeholder-active');
                    }
                });
            }
        },
        
        initializeFilePreview: function() {
            // Make preview functionality globally available
            window.wpaPreviewFile = this.previewFile.bind(this);
            window.wpaClosePreviewModal = this.closePreviewModal.bind(this);
        },
        
        handleAddMedia: function(e) {
            e.preventDefault();

            var self = this;

            // Use the object property, not a local variable
            if (this.mediaFrame) {
                this.mediaFrame.open();
                return;
            }

            this.mediaFrame = wp.media({
                title: 'Add Media Attachments',
                button: {
                    text: 'Attach to Post'
                },
                multiple: true,
                library: {
                    type: 'all'
                }
            });

            this.mediaFrame.on('select', function() {
                var attachments = self.mediaFrame.state().get('selection').toJSON();
                var postId = self.postID;

                if (!postId) {
                    alert('Please save the post first before adding attachments.');
                    return;
                }

                attachments.forEach(function(attachment) {
                    self.attachFileToPost(attachment.id, postId);
                });
            });

            this.mediaFrame.open();
        },
        
        attachFileToPost: function(attachmentId, postId) {
            var self = this;

            $.ajax({
                url: this.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpa_attach_media', // renamed from ij_attach_media
                    attachment_id: attachmentId,
                    post_id: postId,
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.html) {
                        // Remove "no attachments" message if present
                        $('.wpa-no-attachments').remove();
                        // Append new attachment to the list
                        var $newItem = $(response.data.html);
                        self.$container.append($newItem.hide().fadeIn(400));
                        // Re-cache elements and re-init sortable
                        self.cacheElements();
                        self.initSortable();
                        // Trigger reorder to save new order
                        self.handleReorder();
                    } else {
                        console.error('Failed to attach media:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                }
            });
        },
        
        previewFile: function(url, mimeType, title) {
            if (!url || !mimeType) return;
            
            this.$previewTitle.text(title || 'File Preview');
            this.$previewContent.empty();
            
            var $previewElement;
            
            // Handle different file types
            if (mimeType.startsWith('image/')) {
                $previewElement = $('<img>').attr({
                    src: url,
                    alt: title,
                    style: 'max-width: 100%; max-height: 70vh; object-fit: contain;'
                });
            } else if (mimeType.startsWith('video/')) {
                $previewElement = $('<video controls>').attr({
                    src: url,
                    style: 'max-width: 100%; max-height: 70vh;'
                }).prop('preload', 'metadata');
            } else if (mimeType.startsWith('audio/')) {
                $previewElement = $('<audio controls>').attr({
                    src: url,
                    style: 'width: 100%;'
                }).prop('preload', 'metadata');
            } else if (mimeType === 'application/pdf') {
                $previewElement = $('<iframe>').attr({
                    src: url + '#toolbar=1&navpanes=1&scrollbar=1',
                    style: 'width: 100%; height: 70vh; border: none;'
                });
            } else if (mimeType.startsWith('text/') || mimeType.includes('json') || mimeType.includes('xml')) {
                // For text files, try to load and display content
                $previewElement = $('<div>').addClass('text-preview').text('Loading...');
                
                $.get(url)
                    .done(function(data) {
                        $previewElement.html('<pre style="background: #f1f1f1; padding: 15px; border-radius: 4px; overflow: auto; max-height: 60vh;">' + 
                            $('<div>').text(data).html() + '</pre>');
                    })
                    .fail(function() {
                        $previewElement.html('<p>Unable to preview this text file. <a href="' + url + '" target="_blank">Download instead</a></p>');
                    });
            } else {
                // For other file types, show download link
                $previewElement = $('<div>').html(
                    '<div class="file-preview-placeholder" style="text-align: center; padding: 40px;">' +
                    '<div class="dashicons dashicons-media-default" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></div>' +
                    '<h3>Preview not available</h3>' +
                    '<p>This file type cannot be previewed directly.</p>' +
                    '<a href="' + url + '" target="_blank" class="button button-primary">Download File</a>' +
                    '</div>'
                );
            }
            
            this.$previewContent.append($previewElement);
            this.$previewModal.fadeIn(300);
        },
        
        closePreviewModal: function() {
            this.$previewModal.fadeOut(300);
            
            // Clean up media elements to stop playback
            this.$previewContent.find('video, audio').each(function() {
                if (this.pause) this.pause();
                this.currentTime = 0;
            });
        },
        
        handleKeyEvents: function(e) {
            // Close preview modal on Escape key
            if (e.keyCode === 27) { // Escape key
                this.closePreviewModal();
            }
        },
        
        handleReorder: function() {
            var attachmentIds = [];
            
            this.$container.find('.wpa-attachment-item').each(function() {
                var id = $(this).data('attachmentid');
                if (id) {
                    attachmentIds.push(id);
                }
            });
            
            if (attachmentIds.length === 0) return;
            
            // Send new order to server
            $.ajax({
                url: this.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpa_realign', // renamed from ij_realign
                    alignment: attachmentIds,
                    nonce: this.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        console.error('Failed to reorder attachments:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error during reorder:', error);
                }
            });
        },
        
        // Utility method to refresh attachment list
        refreshAttachmentList: function() {
            location.reload();
        },
        
        // Add smooth animations for better UX
        addAttachmentWithAnimation: function($attachment) {
            $attachment.hide().appendTo(this.$container).fadeIn(400);
        },
        
        removeAttachmentWithAnimation: function($attachment) {
            $attachment.fadeOut(400, function() {
                $(this).remove();
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        attachments.init();
        
        // Re-initialize after AJAX requests that might add new content
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.indexOf('admin-ajax.php') !== -1) {
                attachments.cacheElements();
            }
        });
    });
    
    // Make the object globally available for debugging
    window.WP_Attachments = attachments;
    
    return attachments;
    
})(jQuery);