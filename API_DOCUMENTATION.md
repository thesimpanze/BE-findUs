# API Documentation

Base URL lokal:

```text
http://localhost:8000/api
```

Header umum untuk API JSON:

```http
Accept: application/json
Content-Type: application/json
```

Header untuk endpoint yang membutuhkan login:

```http
Authorization: Bearer <token>
```

## Auth

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| POST | `/register` | No | Register user baru dan return token |
| POST | `/login` | No | Login dan return token |
| POST | `/logout` | Yes | Logout token aktif |
| POST | `/forgot-password` | No | Kirim reset password link |
| POST | `/reset-password` | No | Reset password |
| POST | `/email/verification-notification` | Yes | Kirim email verification |
| GET | `/verify-email/{id}/{hash}` | Yes | Verifikasi email via signed URL |

Contoh register:

```json
{
  "name": "User Test",
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password",
  "phone": "08123456789"
}
```

Contoh login:

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

## Subscription & Payment

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| GET | `/subscription` | Yes | Cek status premium, active subscription, dan pending payment |
| POST | `/subscription/upgrade` | Yes | Buat pending payment dan generate Snap token Midtrans |
| POST | `/subscription/cancel` | Yes | Cancel subscription premium yang sudah aktif |
| GET | `/subscription/payments` | Yes | Lihat riwayat payment subscription |
| POST | `/subscription/payment/cancel` | Yes | Cancel payment subscription yang masih pending |
| POST | `/midtrans/notification` | No | Webhook Midtrans untuk update status payment |

Contoh cancel payment:

```json
{
  "order_id": "SUB-20260521000100-1-ABC123"
}
```

Catatan:

- `POST /subscription/upgrade` tidak langsung membuat user premium.
- User jadi premium setelah webhook Midtrans mengubah payment menjadi `paid` dan subscription menjadi `active`.
- `POST /subscription/payment/cancel` hanya untuk payment `pending`.
- `POST /subscription/cancel` untuk subscription yang sudah aktif.

## Location

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| POST | `/location` | Yes | Update lokasi terbaru user ke Redis |
| GET | `/circles/{circle}/locations` | Yes | Ambil lokasi anggota dalam circle |

Contoh update location:

```json
{
  "latitude": -6.2,
  "longitude": 106.816666,
  "battery": 80
}
```

## Circle

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| POST | `/circles/join` | Yes | Join circle memakai referal code |
| POST | `/circles/leave` | Yes | Keluar dari circle orang lain dan kembali ke circle sendiri |

Contoh join circle:

```json
{
  "referal_code": "ABCDE"
}
```

## User

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| GET | `/user` | Yes | Ambil data user login |
