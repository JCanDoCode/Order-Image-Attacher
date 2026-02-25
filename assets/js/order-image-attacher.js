class OrderImagesAttachments {
    constructor() {
        this.init();
    }

    init() {
        this.initImgDropper();
    }

    initImgDropper() {
        const orderId = document.getElementById('post_ID')?.value;

        if (!orderId) return console.log('No order ID');

        const input = document.querySelector('#orderImgUpload');
        
        input.addEventListener('change', () => {this.onImgDrop(orderId, input)});
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
                const img = document.createElement('img');
                img.classList.add('oia-order-image');
                img.src = data[data.length - 1];
                imgContainer.appendChild(img);
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