# ΣΦΡΑΓΙΣ

Εφαρμογή PHP/MySQL για ψηφιακή επικύρωση PDF. Ένας χρήστης ανεβάζει έγγραφο και η εφαρμογή το σφραγίζει με QR. Ο διαχειριστής ορίζει αν η σφραγίδα είναι υποσέλιδο, κεφαλίδα σε κάθε σελίδα, ή ξεχωριστό παράρτημα. Όποιος σαρώνει τον κωδικό βλέπει τα στοιχεία γνησιότητας και μπορεί να κατεβάσει το σφραγισμένο PDF.

## Έκδοση

Ο αριθμός είναι στο αρχείο `VERSION`. Αυξάνεται σε κάθε αλλαγή και φαίνεται διακριτικά πάνω δεξιά στη σελίδα σύνδεσης.

## Ρόλοι

- **Διαχειριστής:** χρήστες, όλα τα έγγραφα, ακύρωση ή διαγραφή οποιουδήποτε, καταγραφές συνδέσεων, ιστορικό μεταφορτώσεων, παράμετροι, έλεγχος/αρχικοποίηση/αντίγραφο/επαναφορά MySQL. Επικυρώνει πάντα αμέσως και μπορεί να επιβεβαιώνει έγγραφα που περιμένουν.
- **Γραμματεία:** βλέπει, επεξεργάζεται, ακυρώνει και διαγράφει όλα τα έγγραφα, καταχωρίζει δικά της και τα επικυρώνει αμέσως. Έχει λίστα «Προς επιβεβαίωση» για τα έγγραφα των υπόλοιπων χρηστών. Η σύνδεση με κωδικό περιορίζεται στα εσωτερικά δίκτυα. Ο σύνδεσμος email επιβεβαίωσης τη συνδέει και εκτός λίστας.
- **Χρήστης:** βλέπει και αναζητά όλα τα έγγραφα, αλλά επεξεργάζεται, ακυρώνει ή διαγράφει μόνο τα δικά του. Βλέπει μόνο τις δικές του κινήσεις αρχείων. Συνδέεται μόνο από τα εσωτερικά δίκτυα που όρισε ο διαχειριστής. Τα έγγραφά του μένουν σε αναμονή μέχρι τη Γραμματεία, εκτός αν ο διαχειριστής ενεργοποιήσει στη φόρμα του την άμεση επικύρωση. Η επιλογή είναι εξ ορισμού κλειστή.

Ένα έγγραφο σε αναμονή δεν έχει QR και ο δημόσιος σύνδεσμός του δεν το εμφανίζει ως γνήσιο.

Ένα ακυρωμένο έγγραφο παραμένει ορατό, με προαιρετική αιτία. Ένα διαγραμμένο δεν εμφανίζεται πουθενά και ο σύνδεσμός του δεν ανοίγει.

## Ασφάλεια

- Κωδικοί με `password_hash`, συνεδρίες HttpOnly / SameSite=Strict, ανανέωση συνεδρίας στη σύνδεση.
- CSRF σε κάθε φόρμα που αλλάζει δεδομένα, prepared statements, αρχεία PDF έξω από τον δημόσιο φάκελο.
- Όριο αποτυχημένων συνδέσεων και όριο άκυρων προσπαθειών QR.
- Ο σύνδεσμος του QR είναι 64 δεκαεξαδικοί χαρακτήρες (256 bit από `random_bytes`). Δεν υπάρχει σειριακός αριθμός στη δημόσια διεύθυνση.

## Απαιτήσεις

- PHP 8.2+ με `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `openssl`
- MariaDB ή MySQL 8, στον ίδιο ή σε άλλον διακομιστή
- [Composer](https://getcomposer.org)
- [`qpdf`](https://qpdf.sourceforge.io/) για τη σφραγίδα QR στην κεφαλίδα, στο υποσέλιδο ή σε σελίδα παραρτήματος

## Εγκατάσταση σε Apache (Ubuntu)

Η βάση μπορεί να είναι ήδη ρυθμισμένη σε άλλον διακομιστή. Στον web server δεν εγκαθίσταται MariaDB ή MySQL. Το `php-mysql` είναι μόνο ο οδηγός σύνδεσης.

### Πακέτα

```bash
sudo apt install apache2 php php-cli php-mysql php-mbstring php-gd php-xml php-zip php-curl unzip qpdf composer
sudo a2enmod rewrite
sudo systemctl reload apache2
```

Με το πακέτο `php` έρχονται και τα `fileinfo`, `json` και `openssl`.

### Αρχεία εφαρμογής

Τα αρχεία μπαίνουν στον κατάλογο της εφαρμογής, για παράδειγμα `/var/www/sfragis`. Το `composer install` τρέχει εκεί, όχι μέσα στο `public/`:

```bash
cd /var/www/sfragis
composer install --no-dev --optimize-autoloader
```

Ο Apache τρέχει ως `www-data`. Ο κώδικας μένει στον root και ο `www-data` γράφει μόνο στο `config/` και στο `storage/`:

```bash
chown -R root:www-data /var/www/sfragis
find /var/www/sfragis -type d -exec chmod 750 {} \;
find /var/www/sfragis -type f -exec chmod 640 {} \;
chown -R www-data:www-data /var/www/sfragis/config /var/www/sfragis/storage
find /var/www/sfragis/config /var/www/sfragis/storage -type d -exec chmod 750 {} \;
```

### Ιστότοπος Apache

Ο δημόσιος κατάλογος είναι ο φάκελος `public/`. Το αρχείο μπαίνει στο `/etc/apache2/sites-available/sfragis.conf`:

```apache
<VirtualHost *:80>
    ServerName sfragis.example.gr
    DocumentRoot /var/www/sfragis/public

    <Directory /var/www/sfragis/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sfragis-error.log
    CustomLog ${APACHE_LOG_DIR}/sfragis-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite sfragis
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Το `AllowOverride All` χρειάζεται για το `public/.htaccess`. Το `setup.php` σερβίρεται ως κανονικό αρχείο. Τα όρια μεταφόρτωσης είναι ήδη στο `public/.user.ini` (`upload_max_filesize = 21M`, `post_max_size = 24M`).

### AppArmor και qpdf

Σε Ubuntu 25.10 και νεότερα το `qpdf` περιορίζεται από το AppArmor και δεν διαβάζει αρχεία κάτω από το `/var/www`. Η επικύρωση τότε αποτυγχάνει με το μήνυμα «Το PDF δεν μπόρεσε να σφραγιστεί», ακόμη και όταν το PDF είναι έγκυρο και χωρίς κωδικό.

Ελέγξτε ότι το `/etc/apparmor.d/qpdf` περιέχει τη γραμμή `include if exists <local/qpdf>` και προσθέστε τοπική εξαίρεση για τον κατάλογο εγκατάστασης:

```bash
sudo mkdir -p /etc/apparmor.d/local
sudo tee /etc/apparmor.d/local/qpdf >/dev/null <<'EOF'
/var/www/sfragis/storage/pdfs/** rw,
/var/www/sfragis/storage/tmp/** rw,
EOF
sudo apparmor_parser -r /etc/apparmor.d/qpdf
```

Αν η εφαρμογή είναι αλλού, αλλάξτε τη διαδρομή. Δεν χρειάζεται επανεκκίνηση του Apache. Άρνηση φαίνεται με:

```bash
sudo aa-status | grep qpdf
sudo dmesg -T | grep -i apparmor | grep qpdf | tail
```

### Βάση

Στον SQL server η βάση δημιουργείται ως `utf8mb4` με collation `utf8mb4_unicode_ci`:

```sql
CREATE DATABASE sfragis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ο χρήστης της βάσης χρειάζεται `SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX` σε αυτή τη βάση, και πρέπει να δέχεται σύνδεση από τη διεύθυνση του web server. Δικαίωμα δημιουργίας νέας βάσης χρειάζεται μόνο αν στο `setup.php` δηλωθεί βάση που δεν υπάρχει ακόμα. Οι στήλες των QR και των αποτυπωμάτων ορίζονται από το schema ως `ascii` / `ascii_bin`.

### Πρώτη εκτέλεση

Ανοίξτε το `setup.php`. Δείχνει ελέγχους για PHP, επεκτάσεις, qpdf, δικαιώματα και όρια μεταφόρτωσης, και έχει ξεχωριστή δοκιμή σύνδεσης MySQL και δοκιμαστική αποστολή SMTP. Μετά δημιουργεί τους πίνακες, τον πρώτο διαχειριστή και, αν συμπληρώθηκαν, τις ρυθμίσεις email. Ο οδηγός κλειδώνει και δεν ξανατρέχει.

Για δοκιμή με τον ενσωματωμένο διακομιστή, χωρίς Apache:

```bash
php -d upload_max_filesize=21M -d post_max_size=24M -S 127.0.0.1:8080 -t public public/router.php
```

## Παράμετροι

Από τον λογαριασμό διαχειριστή ρυθμίζονται δύο διευθύνσεις: το URL που μπαίνει στα νέα QR και το URL του διακομιστή για τους συνδέσμους των email. Μπορούν να διαφέρουν όταν χρησιμοποιείται web proxy. Επίσης το όνομα της κεφαλίδας, η μορφή της ψηφιακής σφραγίδας (υποσέλιδο, κεφαλίδα ή παράρτημα), η επικοινωνία και η ιδιοκτησία/κατασκευή στο υποσέλιδο, η προεπιλεγμένη διάρκεια ισχύος (το 0 αφήνει το έγγραφο χωρίς λήξη), τα εσωτερικά δίκτυα (CIDR IPv4) και τα στοιχεία MySQL. Από την ίδια σελίδα γίνεται έλεγχος σύνδεσης, αρχικοποίηση πινάκων, λήψη και επαναφορά SQL.

Το email ρυθμίζεται ξεχωριστά: διακομιστής SMTP, θύρα, όνομα χρήστη, κωδικός, όνομα και email αποστολέα, και αν κάθε αίτημα επιβεβαίωσης στέλνεται στη Γραμματεία ή και σε άλλη διεύθυνση. Το μήνυμα περιέχει σύνδεσμο «Πατήστε εδώ» που συνδέει τον παραλήπτη ως τη Γραμματεία στην οποία αντιστοιχεί ο σύνδεσμος. Ο κωδικός SMTP φυλάσσεται στον πίνακα ρυθμίσεων και επομένως υπάρχει στα αντίγραφα SQL.

Τα ήδη σφραγισμένα PDF κρατούν το URL της στιγμής που δημιουργήθηκαν.

## Άδειες

Bootstrap 5 (MIT), GFS Didot και Source Sans 3 (OFL), TCPDF (LGPL-3.0), PHPMailer (LGPL-2.1).
