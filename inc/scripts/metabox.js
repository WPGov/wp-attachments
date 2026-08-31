var WP_Attachments = (function($) {
    "use strict";
    
    var attachments = {
        mediaFrame: null, // <-- Add this line

        init: function() {
            this.cacheElements();
            this.detachModal();
            this.bindEvents();
            this.initSortable();
            this.refreshMoveButtons();
            this.initializeFilePreview();
        },

        // The block editor renders metaboxes inside a transformed container.
        // A transformed ancestor becomes the containing block for
        // position:fixed, so the dialog would only cover the metabox panel
        // instead of the viewport. Re-parenting it to <body> fixes that.
        detachModal: function() {
            if (this.$previewModal.length && this.$previewModal.parent()[0] !== document.body) {
                this.$previewModal.appendTo(document.body);
            }
        },
        
        cacheElements: function() {
            this.$container = $('#wpa-attachment-list');
            this.$attachmentItems = $('.wpa-attachment-item');
            this.$addMediaButton = $('.wpa_attach_file');
            this.$previewModal = $('#wpa-preview-modal');
            this.$previewContent = $('#wpa-preview-file');
            this.$previewTitle = $('#wpa-preview-title');
            this.$stats = $('#wpa-attachments-stats');

            // Cache localized variables
            this.editMediaTitle = WP_Attachments_Vars.editMedia || 'Edit Media';
            this.youSureText = WP_Attachments_Vars.youSure || 'Are you sure you want to do this?';
            this.confirmDeleteText = WP_Attachments_Vars.confirmDelete || 'Are you sure you want to delete this permanently?';
            this.postID = WP_Attachments_Vars.postID || 0;
            this.ajaxurl = WP_Attachments_Vars.ajaxurl || '';
            this.nonce = WP_Attachments_Vars.nonce || '';
        },
        
        bindEvents: function() {
            var self = this;
            
            // Add media button click
            $(document).on('click', '.wpa_attach_file', this.handleAddMedia.bind(this));
            
            // Visible move controls. Dragging alone is mouse-only and the grip
            // on its own was not discoverable.
            $(document).on('click', '.wpa-move-up, .wpa-move-down', function (e) {
                e.preventDefault();
                self.moveItem(
                    $(this).closest('.wpa-attachment-item'),
                    $(this).hasClass('wpa-move-up'),
                    this
                );
            });

            // Arrow keys on the grip do the same thing.
            $(document).on('keydown', '.wpa-attachment-drag-handle', function (e) {
                var isUp = (e.key === 'ArrowUp' || e.keyCode === 38);
                var isDown = (e.key === 'ArrowDown' || e.keyCode === 40);
                if (!isUp && !isDown) {
                    return;
                }

                e.preventDefault();
                self.moveItem($(this).closest('.wpa-attachment-item'), isUp, this);
            });

            // Edit: open the WordPress media modal instead of leaving the page.
            // Core saves the fields through its own save-attachment endpoint,
            // so there is no custom handler behind this.
            $(document).on('click', '.wpa-edit-attachment', function (e) {
                // Let modified clicks fall through to the full edit screen.
                if (e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                    return;
                }

                var id = parseInt($(this).closest('.wpa-attachment-item').data('attachmentid'), 10);
                if (!id || typeof wp === 'undefined' || !wp.media) {
                    return; // no JS media library: the href still works
                }

                e.preventDefault();
                self.openEditFrame(id);
            });

            // Preview triggers. Delegated, because rows arrive via AJAX too.
            $(document).on('click', '.wpa-preview-trigger', function(e) {
                e.preventDefault();
                var $trigger = $(this);
                self.previewFile(
                    $trigger.data('url'),
                    $trigger.data('mime'),
                    $trigger.data('title'),
                    this
                );
            });

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
            $(document).on('click', '.wpa-unattach-action, .wpa-delete-action', function(e) {
                var isDelete = $(this).hasClass('wpa-delete-action');
                var message = isDelete ? self.confirmDeleteText : self.youSureText;
                    
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        // Media modal scoped to a single attachment, for editing its details.
        openEditFrame: function (attachmentId) {
            var self = this;

            this.editFrames = this.editFrames || {};

            if (this.editFrames[attachmentId]) {
                this.editFrames[attachmentId].open();
                return;
            }

            var model = wp.media.attachment(attachmentId);

            var frame = wp.media({
                title: this.editMediaTitle,
                // Restrict the library to this one file so the modal opens
                // straight on its details instead of the whole collection.
                library: { post__in: [attachmentId] },
                button: { text: WP_Attachments_Vars.doneEditing || 'Done' },
                multiple: false
            });

            frame.on('open', function () {
                model.fetch();
                frame.state().get('selection').add(model);
            });

            // Core writes each field as it changes, so mirror it back into the
            // row as the user types rather than waiting for the modal to close.
            model.on('change:title', function (m) {
                self.updateRowTitle(attachmentId, m.get('title'));
            });

            frame.on('select close', function () {
                self.updateRowTitle(attachmentId, model.get('title'));
            });

            this.editFrames[attachmentId] = frame;
            frame.open();
        },

        // Keep the row in step with a title edited in the media modal.
        updateRowTitle: function (attachmentId, title) {
            if (typeof title !== 'string') {
                return;
            }

            var $row = $('.wpa-attachment-item[data-attachmentid="' + attachmentId + '"]');
            if (!$row.length) {
                return;
            }

            var display = title.length ? title : (WP_Attachments_Vars.noTitle || '(no title)');

            // .attr() alone is not enough: jQuery caches data attributes, and
            // the preview handler reads them through .data().
            $row.attr('data-title', title).data('title', title);
            $row.find('.wpa-attachment-title a').text(display).attr('title', display);

            var $trigger = $row.find('.wpa-preview-trigger');
            if ($trigger.length) {
                $trigger.attr('data-title', title).data('title', title);

                var template = WP_Attachments_Vars.previewLabel || 'Preview %s';
                $trigger.find('.screen-reader-text').text(template.replace('%s', display));
            }
        },

        // Move one row up or down. Shared by the buttons and the arrow keys.
        moveItem: function ($item, up, focusEl) {
            var $sibling = up
                ? $item.prev('.wpa-attachment-item')
                : $item.next('.wpa-attachment-item');

            if (!$sibling.length) {
                return false;
            }

            if (up) {
                $item.insertBefore($sibling);
            } else {
                $item.insertAfter($sibling);
            }

            this.refreshMoveButtons();

            // Moving the node in the DOM drops focus in some browsers. If the
            // control that was used just became disabled (the row reached an
            // end), fall back to the grip so focus never lands on <body>.
            if (focusEl) {
                if (focusEl.disabled) {
                    $item.find('.wpa-attachment-drag-handle').trigger('focus');
                } else {
                    focusEl.focus();
                }
            }

            this.announcePosition($item);
            this.handleReorder();

            return true;
        },

        // Grey out the moves that would do nothing.
        refreshMoveButtons: function () {
            var $items = $('#wpa-attachment-list').find('.wpa-attachment-item');
            var last = $items.length - 1;

            $items.each(function (i) {
                $(this).find('.wpa-move-up').prop('disabled', i === 0);
                $(this).find('.wpa-move-down').prop('disabled', i === last);
            });
        },

        // Tell screen readers where the row ended up.
        announcePosition: function ($item) {
            var $status = $('#wpa-reorder-status');
            if (!$status.length) {
                return;
            }

            var $all = $('#wpa-attachment-list').find('.wpa-attachment-item');
            var position = $all.index($item) + 1;
            var template = WP_Attachments_Vars.movedTo || 'Moved to position %1$s of %2$s';

            $status.text(
                template.replace('%1$s', position).replace('%2$s', $all.length)
            );
        },

        initSortable: function() {
            var self = this;

            if (!this.$container.length) {
                return;
            }

            // Already initialised: just let jQuery UI pick up new items.
            // Re-running .sortable() would stack duplicate instances, and each
            // one would fire its own reorder request.
            if (this.$container.hasClass('ui-sortable')) {
                this.$container.sortable('refresh');
                return;
            }

            this.$container.sortable({
                items: '.wpa-attachment-item',
                handle: '.wpa-attachment-drag-handle',
                cursor: 'move',
                opacity: 0.7,
                placeholder: 'wpa-attachment-item ui-sortable-placeholder',
                forcePlaceholderSize: true,
                tolerance: 'pointer',
                containment: 'parent',
                // jQuery UI's default cancel selector is
                // "input,textarea,button,select,option". The drag handle is a
                // <button> (so it is keyboard reachable), which that default
                // silently refuses to drag. Drags can only start on `handle`
                // anyway, so dropping "button" here is safe.
                cancel: 'input, textarea, select, option',
                start: function(e, ui) {
                    ui.item.addClass('ui-sortable-helper');
                    ui.placeholder.height(ui.item.height());
                },
                stop: function(e, ui) {
                    ui.item.removeClass('ui-sortable-helper');
                },
                // 'update' fires once, and only when the order actually changed.
                update: function() {
                    self.refreshMoveButtons();
                    self.handleReorder();
                },
                change: function(e, ui) {
                    // Visual feedback during drag
                    ui.placeholder.addClass('ui-sortable-placeholder-active');
                }
            });
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
                title: WP_Attachments_Vars.mediaFrameTitle || 'Add Media Attachments',
                button: {
                    text: WP_Attachments_Vars.mediaFrameButton || 'Attach to Post'
                },
                multiple: true
            });

            this.mediaFrame.on('select', function() {
                var selection = self.mediaFrame.state().get('selection');
                var postId = parseInt(self.postID);

                if (!postId) {
                    window.alert(WP_Attachments_Vars.saveFirst || 'Please save this content before adding attachments.');
                    return;
                }

                selection.each(function(attachment) {
                    // Try to get parent ID from multiple possible locations
                    var attributes = attachment.attributes;
                    var currentParent = parseInt(attributes.uploadedTo || attributes.parent || 0);
                    var attachmentTitle = attributes.title || 'this file';
                    var proceed = true;

                    // If file has a parent and it's not the current post
                    // Note: currentParent might be a string like "123" or "0"
                    if (currentParent > 0 && currentParent !== postId) {
                        var parentTitle = attributes.uploadedToTitle || (attributes.parentObj ? attributes.parentObj.post_title : 'another content');
                        
                        var warning = WP_Attachments_Vars.reattachWarning ||
                            'The file "%1$s" is already attached to "%2$s" (ID: %3$s).';

                        proceed = window.confirm(
                            warning
                                .replace('%1$s', attachmentTitle)
                                .replace('%2$s', parentTitle)
                                .replace('%3$s', currentParent)
                        );
                    }
                    
                    if (proceed) {
                        self.attachFileToPost(attachment.id, postId);
                    }
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
                        // Re-query: the editor may have re-rendered the metabox
                        // since the last cache.
                        self.$container = $('#wpa-attachment-list');
                        if (!self.$container.length) {
                            return;
                        }

                        // Remove "no attachments" placeholder if present
                        $('.wpa-no-attachments').remove();

                        // Append new attachment to the list
                        var $newItem = $(response.data.html);
                        self.$container.append($newItem.hide().fadeIn(400));

                        // Keep the counters in sync without a page reload
                        if (response.data.stats) {
                            $('#wpa-attachments-stats').html(response.data.stats);
                        }

                        // Re-cache elements and let sortable pick up the new row
                        self.cacheElements();
                        self.initSortable();
                        self.refreshMoveButtons();
                        // Persist the new order
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
        
        previewFile: function(url, mimeType, title, trigger) {
            if (!url || !mimeType) return;

            // Remember what opened the modal so focus can go back there.
            this.lastTrigger = trigger || null;

            this.$previewTitle.text(title || WP_Attachments_Vars.previewFallback || 'File Preview');
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
                // For other file types, offer a download link.
                // Built with jQuery rather than string concatenation so the URL
                // can never break out of the attribute.
                $previewElement = $('<div>').addClass('file-preview-placeholder')
                    .css({ textAlign: 'center', padding: '40px 20px' });

                $previewElement.append(
                    $('<div>').addClass('dashicons dashicons-media-default')
                        .css({ fontSize: '48px', width: '48px', height: '48px', color: '#c3c4c7' })
                );
                $previewElement.append(
                    $('<h3>').text(WP_Attachments_Vars.previewUnavailable || 'Preview not available')
                );
                $previewElement.append(
                    $('<a>').addClass('button button-primary')
                        .attr({ href: url, target: '_blank', rel: 'noopener noreferrer' })
                        .text(WP_Attachments_Vars.downloadFile || 'Download file')
                );
            }

            this.$previewContent.append($previewElement);
            this.openPreviewModal();
        },

        openPreviewModal: function() {
            this.$previewModal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('wpa-modal-open');

            var $close = this.$previewModal.find('.wpa-preview-close');
            if ($close.length) {
                $close.trigger('focus');
            }
        },

        closePreviewModal: function() {
            if (!this.$previewModal || !this.$previewModal.hasClass('is-open')) {
                return;
            }

            this.$previewModal.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('wpa-modal-open');

            // Stop playback, then drop the nodes so nothing keeps buffering.
            this.$previewContent.find('video, audio').each(function() {
                if (this.pause) this.pause();
                this.currentTime = 0;
            });
            this.$previewContent.empty();

            if (this.lastTrigger) {
                $(this.lastTrigger).trigger('focus');
                this.lastTrigger = null;
            }
        },

        handleKeyEvents: function(e) {
            // Only act while the modal is actually open.
            if (!this.$previewModal || !this.$previewModal.hasClass('is-open')) {
                return;
            }

            if (e.key === 'Escape' || e.keyCode === 27) {
                this.closePreviewModal();
                return;
            }

            if (e.key === 'Tab' || e.keyCode === 9) {
                this.trapFocus(e);
            }
        },

        // Keep Tab inside the dialog while it is open.
        trapFocus: function(e) {
            var $focusable = this.$previewModal
                .find('a[href], button:not([disabled]), video[controls], audio[controls], iframe, [tabindex]:not([tabindex="-1"])')
                .filter(':visible');

            if (!$focusable.length) {
                return;
            }

            var first = $focusable.first()[0];
            var last = $focusable.last()[0];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
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
        if (!window.WP_Attachments_initialized) {
            attachments.init();
            window.WP_Attachments_initialized = true;
        }
        
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