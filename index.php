<?php
require_once 'includes/header.php';
?>

<section id="Slide">
    <div class="aspect-ratio-169">
        <img src="images/Slide1.jpg" alt="Slide 1">
        <img src="images/Slide2.jpg" alt="Slide 2">
        <img src="images/Slide3.webp" alt="Slide 3">
    </div>
</section>

<style>
    .aspect-ratio-169 {
        display: block;
        position: relative;
        padding-top: 56.25%;
        transition: 0.5s;
        overflow: hidden;
        width: 100%;
    }
    .aspect-ratio-169 img {
        display: block;
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        object-fit: cover;
        transition: 0.5s;
    }
</style>

<script>
    (function() {
        var imgPosition = document.querySelectorAll(".aspect-ratio-169 img");
        var imgContainer = document.querySelector(".aspect-ratio-169");
        if (!imgContainer || imgPosition.length === 0) return;
        imgPosition.forEach(function(img, idx) { img.style.left = idx * 100 + "%"; });
        var index = 0;
        setInterval(function() {
            index = (index + 1) % imgPosition.length;
            imgContainer.style.left = "-" + index * 100 + "%";
        }, 5000);
    })();
</script>

<section class="moinhat">
    <div class="container">
        <h2>MỚI NHẤT HÔM NAY</h2>
        <div class="muahang-list" id="newProducts">
            <div class="loading-text">Đang tải sản phẩm...</div>
        </div>
    </div>
</section>

<style>
    .moinhat { padding: 50px 0; }
    .moinhat h2 { text-align: center; margin-bottom: 20px; font-size: 18px; font-weight: bold; color: #000; }
    .muahang-list { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 30px; }
    .muahang { text-align: center; transition: 0.3s; cursor: pointer; }
    .muahang img { width: 100%; height: auto; display: block; }
    .muahang:hover { transform: scale(1.05); }
    .muahang h3 { margin-top: 10px; font-size: 16px; color: #333; font-weight: normal; }
    .muahang p { font-size: 15px; color: #e30613; font-weight: bold; margin-top: 5px; }
    .product-item { text-decoration: none; color: inherit; }
    .container { width: 1200px; margin: 0 auto; }
    .loading-text { text-align: center; padding: 40px; color: #999; grid-column: 1/5; }
    .loading-text i { font-size: 40px; display: block; margin-bottom: 12px; }
    .product-image-wrapper { position: relative; overflow: hidden; }
    .save-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: #e30613;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 4px;
        z-index: 10;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        white-space: nowrap;
    }
</style>

<script>
    function loadNewProducts() {
        const productList = document.getElementById('newProducts');
        
        fetch('api/get-products.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const products = data.products.slice(0, 4);
                    let html = '';
                    
                    if (products.length === 0) {
                        html = `<div class="loading-text">Không có sản phẩm nào</div>`;
                    } else {
                        products.forEach(p => {
                            html += `
                                <a href="muahang.html?id=${p.id}" class="product-item">
                                    <div class="muahang">
                                        <div class="product-image-wrapper">
                                            <img src="${p.main_image}" alt="${p.name}">
                                            <span class="save-badge">Mới nhất</span>
                                        </div>
                                        <h3>${p.name}</h3>
                                        <p>${parseInt(p.price).toLocaleString()}đ</p>
                                    </div>
                                </a>
                            `;
                        });
                    }
                    productList.innerHTML = html;
                }
            })
            .catch(error => {
                productList.innerHTML = `<div class="loading-text">❌ Lỗi tải sản phẩm</div>`;
            });
    }

    document.addEventListener('DOMContentLoaded', loadNewProducts);
</script>

<?php
require_once 'includes/footer.php';
?>