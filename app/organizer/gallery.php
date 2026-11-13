<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';

requireRole([1]);
requireActiveAccount($conn);
/** @var mysqli $conn */
$userType = currentRole();

include 'includes/header.php';
?>

<style>
    /* Section Labels — inherited from dashboard-styles.php (.section-label) */

    /* Upload Section */
    .upload-section{border-radius:3px;border:none;box-shadow:0 4px 20px rgba(9,23,60,.08);overflow:hidden;background:#fff;margin-bottom:24px;animation:fadeInUp .5s ease both;animation-delay:.1s}
    .upload-section-header{padding:20px 24px 12px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px}
    .upload-section-body{padding:20px 24px 24px}

    /* Upload Box */
    .upload-box{border:2px dashed #cbd5e1;border-radius:3px;padding:28px;background:#f8fafc;transition:all .3s ease;cursor:pointer}
    .upload-box:hover{background:#eff6ff;border-color:#2563eb}
    .upload-box.dragover{background:#eff6ff;border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.08)}
    .upload-box-icon{font-size:2rem;color:#94a3b8;margin-bottom:8px}
    .upload-box-text{font-size:14px;color:#475569;font-weight:500}
    .upload-box-hint{font-size:12px;color:#94a3b8;margin-top:4px}

    /* Image Preview */
    .image-preview-container{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;margin-top:15px}
    .image-preview{position:relative;border-radius:3px;overflow:visible;background:#f1f5f9;min-height:100px}
    .image-preview img{width:100%;height:100px;object-fit:cover;border-radius:3px;display:block;min-height:100px}
    .image-preview .remove-btn{position:absolute;top:6px;right:6px;background:#ef4444;color:white;border:2px solid white;border-radius:50%;width:28px;height:28px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1;z-index:50;padding:0;pointer-events:auto !important;touch-action:manipulation;box-shadow:0 2px 7px rgba(0,0,0,.28);transition:background .2s}
    .image-preview .remove-btn:hover{background:#dc2626}

    /* Post Info Fields */
    .post-field-label{font-size:12px;font-weight:600;letter-spacing:.3px;text-transform:uppercase;color:#6b7280;margin-bottom:4px}
    .post-field-input{border-radius:3px;border:1.5px solid #e5e7eb;padding:10px 14px;font-size:13px;transition:border-color .2s,box-shadow .2s;background-color:#f9fafb;width:100%}
    .post-field-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1);background-color:#fff;outline:none}
    textarea.post-field-input{resize:vertical;min-height:60px}

    /* Publish Button */
    .publish-btn{background:#09173c;color:#fff;border:none;border-radius:3px;padding:10px 28px;font-weight:600;font-size:13px;transition:all .2s ease;display:inline-flex;align-items:center;gap:6px;cursor:pointer}
    .publish-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(9,23,60,.2);color:#fff}
    .publish-btn:disabled{opacity:.7;transform:none}
    .publish-btn.is-loading{pointer-events:none;opacity:.75;cursor:wait}
    .publish-btn.is-loading .btn-label{display:none}
    .publish-btn .btn-loading{display:none}
    .publish-btn.is-loading .btn-loading{display:inline-flex;align-items:center;gap:6px}
    @keyframes btn-spin{to{transform:rotate(360deg)}}
    .publish-btn .spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:btn-spin .6s linear infinite}

    /* Clear / Cancel Button — subtle ghost style to complement publish-btn */
    .clear-btn{background:transparent;color:#475569;border:1.5px solid #cbd5e1;border-radius:3px;padding:10px 24px;font-weight:600;font-size:13px;transition:all .2s ease;display:inline-flex;align-items:center;gap:6px;cursor:pointer;letter-spacing:.2px}
    .clear-btn:hover{background:#f1f5f9;border-color:#94a3b8;color:#09173c;transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .clear-btn:active{transform:translateY(0);background:#e2e8f0}
    .clear-btn i{font-size:11px}

    /* Gallery Posts Section */
    .posts-section{animation:fadeInUp .5s ease both;animation-delay:.2s}

    /* Gallery Post Cards */
    .gallery-post{border-radius:3px;overflow:hidden;box-shadow:0 4px 16px rgba(9,23,60,.07);transition:all .3s ease;background:#fff;border:1px solid #f0f0f0}
    .gallery-post:hover{box-shadow:0 8px 28px rgba(9,23,60,.12);transform:translateY(-3px)}
    .gallery-post-images{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;padding:14px;background:#f8fafc}
    .gallery-post-images img{width:100%;height:150px;object-fit:cover;border-radius:3px;cursor:pointer;transition:transform .3s ease}
    .gallery-post-images img:hover{transform:scale(1.05)}
    .gallery-post-info{padding:18px 20px;background:white}
    .gallery-post-title{font-size:16px;font-weight:700;margin-bottom:6px;color:#09173c}
    .gallery-post-desc{font-size:13px;color:#64748b;margin-bottom:10px;line-height:1.6}
    .gallery-post-meta{display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#94a3b8}
    .gallery-post-actions{display:flex;gap:6px}
    .gallery-post-actions .btn-sm{padding:5px 14px;font-size:12px;border-radius:3px;font-weight:600;transition:all .2s}

    /* Modal Styling */
    .modal-content{border-radius:14px;border:none;overflow:hidden}
    .modal-header.gradient{background:#09173c;color:#fff;border:none;padding:18px 24px}
    .modal-header.gradient .btn-close{filter:invert(1)}
    .modal-body{padding:24px}
    .modal-footer{border-top:1px solid #f0f0f0;padding:14px 24px}

    /* Lightbox */
    .lightbox{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:9999;align-items:center;justify-content:center;flex-direction:column;padding:20px;backdrop-filter:blur(4px)}
    .lightbox.active{display:flex}
    .lightbox img{max-width:90%;max-height:70vh;border-radius:3px;box-shadow:0 8px 40px rgba(0,0,0,.5)}
    .lightbox-close{position:absolute;top:20px;right:30px;color:white;font-size:36px;cursor:pointer;z-index:10000;width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,.1);transition:background .2s}
    .lightbox-close:hover{background:rgba(255,255,255,.25)}
    .lightbox-nav{position:absolute;top:50%;transform:translateY(-50%);color:white;font-size:2.5rem;cursor:pointer;user-select:none;width:52px;height:52px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,.08);transition:background .2s}
    .lightbox-nav:hover{background:rgba(255,255,255,.2)}
    .lightbox-prev{left:20px}
    .lightbox-next{right:20px}
    .lightbox-counter{color:rgba(255,255,255,.7);font-size:13px;margin-top:16px}

    /* Animations & Utilities */
    @keyframes fadeInUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
    .btn-sm{padding:6px 12px;font-size:12px}

    @media (max-width:768px){.upload-section-body{padding:16px}}
    @media print{.page-header,.upload-section,.gallery-post-actions,.lightbox{display:none!important}}
</style>
<body id="page-top" class="body organizer-gallery">
    <?php include 'includes/bar.php'; ?>
    <?php include 'includes/dashboard-styles.php'; ?>
    <?php include 'includes/gallery-page-styles.php'; ?>

    <div class="container-fluid" style="padding-top:20px;min-height:800px">
        <div class="row p-3">
            <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                <div class="card-body p-0">
                

                <!-- Upload Section -->
                
                <div class="upload-section">
                    <div class="upload-section-header">
                        <div><span class="section-eyebrow">01 / Create a collection</span><h2 class="section-title">Publish a gallery post</h2></div>
                    </div>
                    <div class="upload-section-body">
                        <form id="galleryUploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8') ?>">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="post-field-label">Post Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="post-field-input" placeholder="Enter post title..." required>
                                </div>
                                <div class="col-md-6">
                                    <label class="post-field-label">Description <span class="text-danger">*</span></label>
                                    <textarea name="description" class="post-field-input" rows="1" placeholder="Describe this post..." required></textarea>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="upload-box" id="uploadBox">
                                    <div class="text-center">
                                        <div class="upload-box-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                        <div class="upload-box-text">Drag & drop images here or click to browse</div>
                                        <div class="upload-box-hint">JPG, PNG, WebP, or GIF &bull; Max 5 MB each &bull; Up to 10 images</div>
                                        <input type="file" name="gallery_images[]" id="imageInput" class="form-control d-none" accept="image/*" multiple>
                                    </div>
                                    <div class="image-preview-container" id="previewContainer"></div>
                                </div>
                            </div>
                            <div class="gallery-publish-actions">
                                <button type="button" id="clearBtn" class="clear-btn"><i class="bi bi-x-lg"></i> Clear</button>
                                <button type="submit" class="publish-btn" id="publishBtn"><span class="btn-label"><i class="bi bi-upload"></i> Publish Post</span><span class="btn-loading"><span class="spinner"></span> Publishing&hellip;</span></button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Gallery Posts Section -->
                <div class="gallery-collection-heading"><div><span class="section-eyebrow">02 / Published collections</span><h2 class="section-title">Your gallery posts</h2></div><span class="section-meta">League moments, collected in one place.</span></div>
                <div id="galleryPostsContainer" class="row g-4">
                    <!-- Gallery posts will be loaded here -->
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <div class="modal fade" id="editPostModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header gradient">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Gallery Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editGalleryForm" enctype="multipart/form-data">
                        <input type="hidden" id="editPostId" name="post_id">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="post-field-label">Post Title <span class="text-danger">*</span></label>
                                <input type="text" id="editTitle" name="title" class="post-field-input" required>
                            </div>
                            <div class="col-md-6">
                                <label class="post-field-label">Description <span class="text-danger">*</span></label>
                                <textarea id="editDescription" name="description" class="post-field-input" rows="2" required></textarea>
                            </div>
                        </div>
                        <label class="post-field-label" style="margin-bottom:8px">Current Images</label>
                        <div class="image-preview-container mb-3" id="editExistingImages"></div>
                        <input type="hidden" id="editRemovedImages" name="removed_images">

                        <label class="post-field-label" style="margin-top:12px;margin-bottom:8px">Add New Images</label>
                        <div class="upload-box" id="editUploadBox">
                            <div class="text-center">
                                <div class="upload-box-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                <div class="upload-box-text" style="margin-bottom:8px">Drag & drop or click to select</div>
                                <input type="file" id="editImageInput" name="new_images[]" class="form-control d-none" accept="image/*" multiple>
                                <button type="button" class="btn btn-sm mt-2" style="background:#09173c;color:#fff;border:none;border-radius:3px;padding:6px 18px;font-weight:600" onclick="document.getElementById('editImageInput').click()">
                                    <i class="bi bi-folder-fill me-1"></i>Select Images
                                </button>
                            </div>
                            <div class="image-preview-container mt-3" id="editNewPreview"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="clear-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                    <button type="button" class="publish-btn" id="saveEditBtn"><span class="btn-label"><i class="bi bi-check-lg"></i> Save Changes</span><span class="btn-loading"><span class="spinner"></span> Saving&hellip;</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Lightbox Modal -->
    <div class="modal fade" id="imageLightbox" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="background:rgba(0,0,0,.95);border:none;border-radius:3px;overflow:hidden">
                <div class="modal-header border-0" style="background:transparent;padding:12px 20px">
                    <button type="button" class="btn-close btn-close-white gallery-lightbox-close" data-bs-dismiss="modal" aria-label="Close image"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="lightboxImage" src="" alt="" class="img-fluid" style="max-height:80vh;object-fit:contain;border-radius:3px">
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        const uploadBox = document.getElementById('uploadBox');
        const imageInput = document.getElementById('imageInput');
        const previewContainer = document.getElementById('previewContainer');
        let selectedFiles = [];

        // Delegation keeps removal working whenever the preview list is rebuilt.
        previewContainer.addEventListener('click', event => {
            const button = event.target.closest('.remove-btn');
            if (!button) return;
            event.preventDefault();
            event.stopPropagation();
            const previews = [...previewContainer.querySelectorAll('.image-preview')];
            const index = previews.indexOf(button.closest('.image-preview'));
            if (index >= 0) removeImage(index);
        });

        uploadBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadBox.classList.add('dragover');
        });

        uploadBox.addEventListener('dragleave', (e) => {
            if (!uploadBox.contains(e.relatedTarget)) {
                uploadBox.classList.remove('dragover');
            }
        });

        uploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadBox.classList.remove('dragover');
            addFiles(e.dataTransfer.files);
        });

        uploadBox.addEventListener('click', (e) => {
            if (e.target.closest('.remove-btn') || e.target.closest('#previewContainer')) return;
            imageInput.click();
        });

        // Stop the synthetic click from bubbling back to uploadBox (prevents double-trigger)
        imageInput.addEventListener('click', (e) => e.stopPropagation());

        imageInput.addEventListener('change', () => {
            addFiles(imageInput.files);
            imageInput.value = '';
        });

        function addFiles(fileList) {
            Array.from(fileList).forEach(file => {
                if (file.type.startsWith('image/') && selectedFiles.length < 10) selectedFiles.push(file);
            });
            updatePreview();
        }

        function updatePreview() {
            previewContainer.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
                const img = document.createElement('img');
                img.alt = '';
                img.onload = function() { URL.revokeObjectURL(this.src); };
                img.onerror = function() {
                    URL.revokeObjectURL(this.src);
                    this.style.background = '#e2e8f0';
                    this.removeAttribute('src');
                };
                img.src = URL.createObjectURL(file);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'remove-btn';
                btn.innerHTML = '&times;';
                btn.title = 'Remove image';
                btn.setAttribute('aria-label','Remove image');
                preview.appendChild(img);
                preview.appendChild(btn);
                previewContainer.appendChild(preview);
            });
            if (selectedFiles.length) {
                const countDiv = document.createElement('div');
                countDiv.style.cssText = 'grid-column:1/-1;font-size:12px;color:#64748b;margin-top:8px';
                countDiv.textContent = selectedFiles.length + ' image(s) selected';
                previewContainer.appendChild(countDiv);
            }
        }

        function removeImage(index) {
            selectedFiles.splice(index, 1);
            updatePreview();
        }

        document.getElementById('clearBtn').addEventListener('click', () => {
            selectedFiles = [];
            previewContainer.innerHTML = '';
            document.getElementById('galleryUploadForm').reset();
        });

        // Form submission
        document.getElementById('galleryUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();

            if (selectedFiles.length === 0) {
                Swal.fire({ icon: 'warning', title: 'No Images', text: 'Please select at least one image.' });
                return;
            }

            const publishBtn = document.getElementById('publishBtn');
            if (publishBtn.classList.contains('is-loading')) return;
            publishBtn.classList.add('is-loading');
            publishBtn.disabled = true;

            Swal.fire({
                title: 'Publishing...',
                text: 'Please wait.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData(this);
            formData.delete('gallery_images[]');
            selectedFiles.forEach(file => formData.append('gallery_images[]', file));
            formData.append('uploadGallery', true);

            $.ajax({
                url: 'action/galleryAction.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    Swal.close();
                    publishBtn.classList.remove('is-loading');
                    publishBtn.disabled = false;
                    if (res.status === 201) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Gallery Post Created',
                            text: res.msg,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Upload Failed', text: res.msg || 'An error occurred' });
                    }
                },
                error: function() {
                    Swal.close();
                    publishBtn.classList.remove('is-loading');
                    publishBtn.disabled = false;
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to upload. Please try again.' });
                }
            });
        });

        // Load gallery posts
        function getImageUrl(filename) {
            if (filename && (filename.startsWith('https://res.cloudinary.com/') || filename.startsWith('http://res.cloudinary.com/'))) {
                return filename;
            }
            return '../../assets/uploads/' + encodeURIComponent(filename);
        }

        function loadGalleryPosts() {
            $.ajax({
                url: 'action/galleryAction.php',
                type: 'POST',
                data: { getGalleryPosts: true },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 200 && res.posts) {
                        displayGalleryPosts(res.posts);
                    }
                }
            });
        }

        function displayGalleryPosts(posts) {
            const container = document.getElementById('galleryPostsContainer');
            container.innerHTML = '';
            window.galleryPostsById = new Map(posts.map(post => [Number(post.id), post]));

            if (posts.length === 0) {
                container.innerHTML = '<div class="col-12"><div class="alert alert-info text-center py-4" style="border-radius:3px;background:#f0f7ff;border:1px dashed #bfdbfe"><i class="bi bi-images d-block mb-2" style="font-size:2rem;color:#94a3b8"></i><strong>No gallery posts yet</strong><br><span class="text-muted small">Create your first post using the form above!</span></div></div>';
                return;
            }

            posts.forEach(post => {
                const postHtml = `
                    <div class="col-12">
                        <div class="gallery-post">
                            <div class="gallery-post-images">
                                ${post.images.map(img => `
                                    <img src="${getImageUrl(img.filename)}" 
                                         alt="${escapeHtml(post.title)}"
                                         onclick="openLightbox('${getImageUrl(img.filename)}')"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageLightbox">
                                `).join('')}
                            </div>
                            <div class="gallery-post-info">
                                <div class="gallery-post-title">${escapeHtml(post.title)}</div>
                                <div class="gallery-post-desc">${escapeHtml(post.description)}</div>
                                <div class="gallery-post-meta">
                                    <span>
                                        <i class="bi bi-images me-1"></i>
                                        ${post.images.length} image(s)
                                    </span>
                                    <span>
                                        <i class="bi bi-calendar3 me-1"></i>
                                        ${formatDate(post.created_at)}
                                    </span>
                                    <div class="gallery-post-actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-gallery-post" data-post-id="${Number(post.id)}">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-gallery-post" data-post-id="${Number(post.id)}">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += postHtml;
            });
        }

        document.getElementById('galleryPostsContainer').addEventListener('click',event=>{const editButton=event.target.closest('.edit-gallery-post');if(editButton){const post=window.galleryPostsById.get(Number(editButton.dataset.postId));if(post)editPost(post.id,post.title,post.description,post.images);return;}const deleteButton=event.target.closest('.delete-gallery-post');if(deleteButton)deletePost(Number(deleteButton.dataset.postId));});

        function openLightbox(src) {
            document.getElementById('lightboxImage').src = src;
        }

        document.querySelector('#imageLightbox .gallery-lightbox-close')?.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            const modalElement = document.getElementById('imageLightbox');
            // Use the modal's working backdrop-dismiss path; Bootstrap 4 and 5
            // are both loaded by the legacy layout, so their JS APIs can conflict.
            modalElement.click();
        });

        function deletePost(postId) {
            Swal.fire({
                title: 'Delete Gallery Post?',
                text: 'This will permanently delete this post and all its images. This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it',
                confirmButtonColor: '#d33',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleting...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                    $.ajax({
                        url: 'action/galleryAction.php',
                        type: 'POST',
                        data: { deleteGalleryPost: true, post_id: postId, csrf_token: <?= json_encode(csrfToken()) ?> },
                        dataType: 'json',
                        success: function(res) {
                            Swal.close();
                            if (res.status === 200) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted',
                                    text: res.msg,
                                    timer: 1000,
                                    showConfirmButton: false
                                }).then(() => {
                                    loadGalleryPosts();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: res.msg
                                });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete. Please try again.' });
                        }
                    });
                }
            });
        }

        let editNewFiles = [];
        let removedImageIds = [];

        // Edit upload box drag & drop
        const editUploadBox = document.getElementById('editUploadBox');
        const editImageInput = document.getElementById('editImageInput');

        editUploadBox.addEventListener('dragover', (e) => { e.preventDefault(); editUploadBox.classList.add('dragover'); });
        editUploadBox.addEventListener('dragleave', (e) => { if (!editUploadBox.contains(e.relatedTarget)) editUploadBox.classList.remove('dragover'); });
        editUploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            editUploadBox.classList.remove('dragover');
            addEditFiles(e.dataTransfer.files);
        });
        editUploadBox.addEventListener('click', (e) => {
            if (e.target.closest('.remove-btn') || e.target.closest('button') || e.target.closest('#editNewPreview') || e.target.closest('#editExistingImages')) return;
            editImageInput.click();
        });
        editImageInput.addEventListener('click', (e) => e.stopPropagation());
        editImageInput.addEventListener('change', () => {
            addEditFiles(editImageInput.files);
            editImageInput.value = '';
        });

        function addEditFiles(fileList) {
            Array.from(fileList).forEach(file => { if (file.type.startsWith('image/')) editNewFiles.push(file); });
            updateEditNewPreview();
        }

        function updateEditNewPreview() {
            const container = document.getElementById('editNewPreview');
            container.innerHTML = '';
            editNewFiles.forEach((file, index) => {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
                const img = document.createElement('img');
                img.alt = '';
                img.onload = function() { URL.revokeObjectURL(this.src); };
                img.onerror = function() {
                    URL.revokeObjectURL(this.src);
                    this.style.background = '#e2e8f0';
                    this.removeAttribute('src');
                };
                img.src = URL.createObjectURL(file);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'remove-btn';
                btn.innerHTML = '&times;';
                btn.title = 'Remove image';
                btn.setAttribute('aria-label','Remove image');
                btn.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); editNewFiles.splice(index, 1); updateEditNewPreview(); });
                preview.appendChild(img);
                preview.appendChild(btn);
                container.appendChild(preview);
            });
        }

        function editPost(postId, title, description, images) {
            removedImageIds = [];
            editNewFiles = [];
            document.getElementById('editPostId').value = postId;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDescription').value = description;
            document.getElementById('editNewPreview').innerHTML = '';
            document.getElementById('editRemovedImages').value = '';

            // Render existing images
            const container = document.getElementById('editExistingImages');
            container.innerHTML = '';
            if (images.length === 0) {
                container.innerHTML = '<p class="text-muted small">No existing images.</p>';
            } else {
                images.forEach(img => {
                    const preview = document.createElement('div');
                    preview.className = 'image-preview';
                    preview.id = 'existing-img-' + img.id;
                    const el = document.createElement('img');
                    el.src = getImageUrl(img.filename);
                    el.alt = '';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'remove-btn';
                    btn.innerHTML = '&times;';
                    btn.title = 'Remove image';
                    btn.setAttribute('aria-label','Remove image');
                    btn.addEventListener('click', event => {
                        event.preventDefault();
                        event.stopPropagation();
                        removedImageIds.push(img.id);
                        document.getElementById('editRemovedImages').value = removedImageIds.join(',');
                        preview.remove();
                    });
                    preview.appendChild(el);
                    preview.appendChild(btn);
                    container.appendChild(preview);
                });
            }

            new bootstrap.Modal(document.getElementById('editPostModal')).show();
        }

        document.getElementById('saveEditBtn').addEventListener('click', () => {
            const title = document.getElementById('editTitle').value.trim();
            const description = document.getElementById('editDescription').value.trim();
            if (!title || !description) {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Title and description are required.' });
                return;
            }

            const saveBtn = document.getElementById('saveEditBtn');
            if (saveBtn.classList.contains('is-loading')) return;
            saveBtn.classList.add('is-loading');
            saveBtn.disabled = true;

            Swal.fire({
                title: 'Saving changes...',
                text: 'Please wait while we update your post.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('updateGallery', true);
            formData.append('post_id', document.getElementById('editPostId').value);
            formData.append('title', title);
            formData.append('description', description);
            formData.append('removed_images', document.getElementById('editRemovedImages').value);
            formData.append('csrf_token', <?= json_encode(csrfToken()) ?>);
            editNewFiles.forEach(file => formData.append('new_images[]', file));

            $.ajax({
                url: 'action/galleryAction.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    Swal.close();
                    saveBtn.classList.remove('is-loading');
                    saveBtn.disabled = false;
                    if (res.status === 200) {
                        bootstrap.Modal.getInstance(document.getElementById('editPostModal')).hide();
                        Swal.fire({ icon: 'success', title: 'Updated', text: res.msg, timer: 1500, showConfirmButton: false })
                            .then(() => loadGalleryPosts());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.msg });
                    }
                },
                error: function() {
                    Swal.close();
                    saveBtn.classList.remove('is-loading');
                    saveBtn.disabled = false;
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save changes. Please try again.' });
                }
            });
        });

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text ?? '').replace(/[&<>"']/g, m => map[m]);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Show loading state
        document.getElementById("galleryPostsContainer").innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary" role="status" style="width:2.5rem;height:2.5rem"></div><div class="text-muted mt-2 small">Loading gallery posts...</div></div>';

        // Load posts on page load
        $(document).ready(function() {
            loadGalleryPosts();
        });
    </script>
</body>
