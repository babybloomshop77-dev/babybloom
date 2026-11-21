# Запуск BabyBloom на сервере (DigitalOcean, Docker + git)

Сервер: Ubuntu 24.04. Сайт работает через Docker: PHP-FPM, MySQL и Caddy (он сам получает HTTPS-сертификат).

## 1. Создайте сервер (Droplet) в DigitalOcean

- **Image:** Ubuntu 24.04 (LTS)
- **Plan:** Basic → Regular (SSD), 1 ГБ (около 6 $/мес) хватит для старта, при нехватке памяти можно увеличить до 2 ГБ (около 12 $/мес)
- **Region:** Frankfurt (или Amsterdam)
- **Authentication:** **SSH key** → New SSH Key. Ключ скопируйте командой `pbcopy < ~/.ssh/id_ed25519.pub` и вставьте. Пароль не выбирайте.
- **Hostname:** `babybloom`

После создания скопируйте **IPv4-адрес** сервера (дальше `IP`).

## 2. Домен

У регистратора babybloom.am пропишите DNS-записи:

| Тип | Имя | Значение |
|---|---|---|
| **A** | `@` | `IP` |
| **CNAME** | `www` | `babybloom.am` |

Старые записи **A** (`164.90.221.98`) и **AAAA** (`2a03:b0c0…`) удалите. Домен должен указывать на сервер до первого запуска, иначе Caddy не получит сертификат.

## 3. Первичная настройка сервера (один раз)

С вашего компьютера, из папки проекта:

```bash
ssh root@IP 'bash -s' < deploy/bootstrap-droplet.sh
```

Скрипт обновит систему, поставит Docker, включит файрвол и swap, создаст ключ для GitHub. В конце он напечатает **публичный ключ**. Добавьте его в GitHub: репозиторий → **Settings → Deploy keys → Add deploy key** (галочку Write не ставьте).

## 4. Код на сервере (через git)

Сначала загрузите актуальный код в GitHub (на вашем компьютере):

```bash
git add -A && git commit -m "Production setup" && git push
```

Потом на сервере (`ssh root@IP`):

```bash
git clone git@github.com:babybloomshop77-dev/babybloom.git /opt/babybloom
cd /opt/babybloom
cp .env.prod.example .env.prod
```

Заполните `.env.prod` (`nano .env.prod`):
- `APP_KEY`: выполните на сервере `echo "base64:$(openssl rand -base64 32)"` и вставьте результат;
- `DB_PASSWORD` и `DB_ROOT_PASSWORD`: два разных длинных пароля из букв и цифр (например, `openssl rand -hex 16`);
- `APP_URL` и `APP_DOMAIN` уже настроены на `babybloom.am`.

## 5. Запуск

```bash
cd /opt/babybloom
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

Первая сборка занимает несколько минут. Миграции применятся сами.

## 6. Перенос ваших данных (один раз)

На вашем компьютере соберите данные и отправьте на сервер:

```bash
./deploy/export-data.sh
scp deploy/data/database.sql deploy/data/uploads.tar.gz root@IP:/root/
```

На сервере:

```bash
cd /opt/babybloom
alias dc='docker compose --env-file .env.prod -f docker-compose.prod.yml'
dc exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < /root/database.sql
dc exec -T app tar -xzf - -C /var/www/storage/app/public < /root/uploads.tar.gz
dc exec -T app chown -R www-data:www-data /var/www/storage
dc exec app php artisan admin:password
```

Последняя команда задаёт **новый пароль админа** (не меньше 12 символов). Пока вы её не выполнили, пароль остаётся `babybloom`, поэтому делайте это сразу после импорта.

> ⚠ Данные переносятся **один раз**. Повторный импорт сотрёт то, что вы внесли на сервере.

## 7. Готово

Откройте https://babybloom.am и войдите (`admin@babybloom.am` и ваш новый пароль).

## Обновления

Измените код → `git push` на компьютере → на сервере:

```bash
cd /opt/babybloom && ./deploy/update.sh
```

Скрипт подтянет код, пересоберёт и перезапустит сайт. Новые миграции применятся автоматически.

## Полезное (на сервере)

```bash
alias dc='docker compose --env-file .env.prod -f docker-compose.prod.yml'
dc ps                 # состояние
dc logs -f app        # логи приложения
dc logs -f web        # логи Caddy (HTTPS, домен)
dc restart            # перезапуск
```

## Резервные копии

Раз в неделю сохраняйте базу на свой компьютер:

```bash
ssh root@IP "cd /opt/babybloom && docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T mysql sh -c 'mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" --no-tablespaces \"\$MYSQL_DATABASE\"'" > backup-$(date +%F).sql
```

В DigitalOcean можно включить **Backups** (около 20% от цены сервера). Это копия всего сервера целиком, включая фото.

## Если что-то не работает

| Проблема | Что делать |
|---|---|
| Сайт не открывается по домену | Проверьте DNS: `dig +short babybloom.am` должен показать `IP`. Логи Caddy: `dc logs web` |
| Ошибка сертификата | Домен ещё не указывает на сервер, подождите и перезапустите: `dc restart web` |
| 500 ошибка | `dc logs app` |
| Нет фото | Не выполнена команда распаковки `uploads.tar.gz` (шаг 6) |
