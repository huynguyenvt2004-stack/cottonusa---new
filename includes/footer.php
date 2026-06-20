    <section class="contact">
        <h2>Liên hệ với chúng tôi</h2>
        <div class="social-group">
            <a href="https://web.facebook.com/03.august4/" target="_blank"><img src="images/Facebook.jpeg" alt="Facebook"></a>
            <a href="https://www.instagram.com/ming.quann" target="_blank"><img src="images/instagram.jpeg" alt="Instagram"></a>
            <a href="https://www.tiktok.com/@03_august4" target="_blank"><img src="images/tiktok.jpeg" alt="TikTok"></a>
        </div>
        <h3>Nhận bản tin của chúng tôi</h3>
        <form class="newsletter-form">
            <input type="email" class="newsletter-input" placeholder="Nhập email của bạn...">
            <button type="submit" class="submit-btn">→</button>
        </form>
    </section>

    <div class="footer-top">
        <div class="footer-column">
            <h4>Thông tin</h4>
            <ul>
                <li><a href="#">About us</a></li>
                <li><a href="#">Liên hệ chúng tôi</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Hỗ trợ khách hàng</h4>
            <ul>
                <li><a href="#">Chính sách đổi trả & bảo hành</a></li>
                <li><a href="#">Chính sách vận chuyển</a></li>
                <li><a href="#">Điều khoản dịch vụ</a></li>
                <li><a href="#">Chính sách bảo mật</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Dịch vụ khách hàng</h4>
            <ul>
                <li>Hotline: 0865943240</li>
                <li>Email: mingquan2004@gmail.com</li>
                <li>Thứ Hai - Thứ 7</li>
                <li>08:00 ~ 22:00</li>
            </ul>
        </div>
    </div>

    <script src="js/cart-auth.js"></script>
    <script>
        (function initHeaderDropdowns() {
            const cartBtn = document.getElementById('cartBtn');
            const cartDropdown = document.getElementById('cartDropdown');
            const authBtn = document.getElementById('authBtn');
            const authDropdown = document.getElementById('authDropdown');
            const overlay = document.getElementById('dropdownOverlay');

            if (!cartBtn || !cartDropdown || !authBtn || !authDropdown || !overlay) return;

            function closeAll() {
                cartDropdown.classList.remove('open');
                authDropdown.classList.remove('open');
                overlay.classList.remove('active');
            }

            cartBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = cartDropdown.classList.contains('open');
                closeAll();
                if (!isOpen) {
                    cartDropdown.classList.add('open');
                    overlay.classList.add('active');
                }
            });

            authBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = authDropdown.classList.contains('open');
                closeAll();
                if (!isOpen) {
                    authDropdown.classList.add('open');
                    overlay.classList.add('active');
                }
            });

            cartDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
            authDropdown.addEventListener('click', function(e) { e.stopPropagation(); });

            overlay.addEventListener('click', function() { closeAll(); });
            document.addEventListener('click', function() { closeAll(); });
        })();
    </script>
</body>
</html>