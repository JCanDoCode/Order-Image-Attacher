class OrderImagesAttachments {
    constructor() {
        this.init();
    }

    init() {
        console.log('OIA init')
        this.initImgDropper();
        this.initDownloadAll();
        this.addNotice();
    }

    initImgDropper() {
        const orderId = document.getElementById('post_ID')?.value;

        if (!orderId) return console.log('No order ID');

        const input = document.querySelector('#orderImgUpload');
        
        input.addEventListener('change', () => {this.onImgDrop(orderId, input)});
    }

    initDownloadAll() {
        const downloadAllBtn = document.querySelector('#oiaDownloadAll');

        if (!downloadAllBtn) return;

        downloadAllBtn.addEventListener('click', this.downloadAll);
    }

    addNotice() {
        const oiaImgs = document.querySelectorAll('.oia-order-image');

        if (!oiaImgs.length) return;

        const orderNotes = document.querySelector('#woocommerce-order-notes');
        const orderNotesInside = document.querySelector('#woocommerce-order-notes .inside');
        const oiaNotice = document.createElement('p');
        oiaNotice.classList.add('oia-notice');
        oiaNotice.textContent = `${oiaImgs.length} Image(s) Attached`;
        orderNotes.insertBefore(oiaNotice, orderNotesInside);
    }

    downloadAll() {
        const images = document.querySelectorAll('.oia-image-download');

        if (images.length < 1) return;

        images.forEach(img => {
            setTimeout(() => {
                img.click();
            }, 300)
        })
    }

    async onImgDrop(orderId, input) {
        const imgContainer = document.querySelector('#OIAOrderImages');
        imgContainer.classList.add('uploading');
        const formData = new FormData();

        [...input.files].forEach(file => {
            formData.append('order_images[]', file);
        });

        formData.append('action', 'add_order_meta');
        formData.append('order_id', orderId);
        formData.append('nonce', orderImagesAttachmentsVars.nonce);

        try {
            fetch(orderImagesAttachmentsVars.ajaxUrl, {
                method: 'POST',
                body: formData,
            })
            .then(res => res.json())
            .then(data => {
                const a = document.createElement('a');
                a.classList.add('oia');
                const img = document.createElement('img');
                img.classList.add('oia-order-image');
                img.src = data[data.length - 1];
                a.appendChild(img)
                imgContainer.appendChild(a);
                console.log(data);
            });
        } catch (error) {
            console.error('AJAX failed', error);
        } finally {
            imgContainer.classList.remove('uploading');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {new OrderImagesAttachments();});