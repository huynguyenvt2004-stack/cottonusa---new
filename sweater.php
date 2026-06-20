<?php
require_once 'includes/header.php';
?>

<section class="sweater">
    <div class="container">
        <h2>TẤT CẢ SẢN PHẨM</h2>
        <div class="muahang-list" id="productList">
            <div class="loading-text">
                <i class="fas fa-spinner fa-spin"></i>
                Đang tải sản phẩm...
            </div>
        </div>
    </div>
</section>

<style>
    .muahang-list {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 30px;
    }
    .muahang {
        text-align: center;
        transition: 0.3s;
        cursor: pointer;
    }
    .muahang img {
        width: 100%;
        height: auto;
        display: block;
    }
    .muahang:hover {
        transform: scale(1.05);
    }
    .muahang h3 {
        margin-top: 10px;
        font-size: 16px;
        color: #333;
        font-weight: normal;
    }
    .muahang p {
        font-size: 15px;
        color: #e30613;
        font-weight: bold;
        margin-top: 5px;
    }
    .product-item {
        text-decoration: none;
        color: inherit;
    }
    .container {
        width: 1200px;
        margin: 0 auto;
    }
    .sweater {
        padding: 50px 0;
    }
    .sweater h2 {
        text-align: center;
        margin-bottom: 20px;
        font-size: 18px;
        font-weight: bold;
        color: #000;
    }
    .loading-text {
        text-align: center;
        padding: 40px;
        color: #999;
        grid-column: 1/5;
    }
    .loading-text i {
        font-size: 40px;
        display: block;
        margin-bottom: 12px;
    }
</style>

<script>
    function loadProducts() {
        const productList = document.getElementById('productList');
        
        fetch('api/get-products.php')
            .then(response => {
                if (!response.ok) throw new Error('HTTP error ' + response.status);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const products = data.products;
                    let html = '';
                    const filterProducts = products.filter(p => p.category === 'Sweater');
                    
                    if (filterProducts.length === 0) {
                        html = `<div class="loading-text">Không có sản phẩm Sweater nào</div>`;
                    } else {
                        filterProducts.forEach(p => {
                            html += `
                                <a href="muahang.html?id=${p.id}" class="product-item">
                                    <div class="muahang">
                                        <img src="${p.main_image}" alt="${p.name}">
                                        <h3>${p.name}</h3>
                                        <p>${parseInt(p.price).toLocaleString()}đ</p>
                                    </div>
                                </a>
                            `;
                        });
                    }
                    productList.innerHTML = html;
                } else {
                    productList.innerHTML = `<div class="loading-text">❌ ${data.message}</div>`;
                }
            })
            .catch(error => {
                productList.innerHTML = `<div class="loading-text">❌ Lỗi: ${error.message}</div>`;
                console.error('Error:', error);
            });
    }

    document.addEventListener('DOMContentLoaded', loadProducts);
</script>

<?php
require_once 'includes/footer.php';
?>