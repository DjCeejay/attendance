# ARTSCI Attendance — Office Local Network, Dual Wi-Fi & HTTPS Setup

This guide details how to configure the physical office computer's network addressing, bridge both office Wi-Fi networks (Office Wi-Fi A and Office Wi-Fi B), configure Split-Horizon DNS, and enable seamless HTTPS for mobile PWA devices.

---

## 1. Network Topology & Subnet Overview

The ARTSCI office operates two internet connections:
* **Office Wi-Fi A**: ZTE 5G router upstream connected to a **Tenda TX2 Pro** router (Default LAN subnet: `192.168.0.0/24`).
* **Office Wi-Fi B**: **Airtel Router** (Default LAN subnet: `192.168.8.0/24` or `192.168.1.0/24`).

### Addressing Objective:
1. The Windows office server must have a **predictable, static local IP address** (e.g. `192.168.0.100`).
2. Staff connected to **either** Office Wi-Fi A or Office Wi-Fi B must be able to reach the server.
3. No public inbound port forwarding is ever opened on either router.

---

## 2. Setting a Stable LAN IP on the Windows Server

### Option A (Recommended): Router DHCP Reservation
1. On the Windows computer, open PowerShell and find its physical Ethernet MAC address:
   ```powershell
   Get-NetAdapter | Select-Object Name, MacAddress, Status
   ```
2. Log into the Tenda TX2 Pro router management page (`http://192.168.0.1` or `http://tendawifi.com`).
3. Navigate to **Administration** &rarr; **LAN Parameters** / **DHCP Reservation** (or **Static IP Assignment**).
4. Add a new reservation:
   - **MAC Address**: Enter the server's MAC address.
   - **Reserved IP Address**: `192.168.0.100`
   - **Remark**: `ARTSCI Attendance Server`
5. Save and reboot the router or reconnect the server Ethernet cable.
6. Verify in PowerShell with `ipconfig` that IPv4 Address is now `192.168.0.100`.

---

## 3. Bridging Office Wi-Fi A and Office Wi-Fi B

Because Wi-Fi A (Tenda) and Wi-Fi B (Airtel) are typically separate subnets, a phone on Wi-Fi B cannot reach an IP on Wi-Fi A without bridging:

### Architecture 1: Dual-Homed Server (Simplest & Most Reliable)
Connect the Windows office computer to **both** routers:
- **Primary NIC (Ethernet)**: Plugged directly into a LAN port on the Tenda TX2 Pro (`192.168.0.100`).
- **Secondary NIC (Wi-Fi or USB-to-Ethernet Adapter)**: Connected to the Airtel router network (`192.168.8.100`).
- Windows natively handles both network adapters simultaneously!
- In `config/attendance.php`, both subnets are authorized:
  ```env
  OFFICE_LAN_SUBNETS="192.168.0.0/24,192.168.8.0/24"
  ```
- Result: Phones connected to Wi-Fi A reach the server via `192.168.0.100`. Phones connected to Wi-Fi B reach the server via `192.168.8.100`.

### Architecture 2: LAN-to-LAN Bridging (Single Subnet)
- Disable DHCP on the Airtel router, assign its LAN IP as `192.168.0.2`, and plug an Ethernet cable from its LAN port to a LAN port on the Tenda router.
- Result: Both routers broadcast Wi-Fi under a unified single subnet (`192.168.0.0/24`). Every device accesses the server at `192.168.0.100`.

---

## 4. HTTPS, Domain & PWA Configuration

### Why HTTPS is Mandatory
Modern smartphone browsers (iOS Safari, Android Chrome) enforce the Web Security Standard:
- **Service Workers (`sw.js`) and PWA installation are blocked on plain HTTP** (except on `localhost`).
- **WebAuthn passkey prompts (`navigator.credentials.get`) throw SecurityError on plain HTTP**.
- Self-signed certificates produce red browser warning screens and are rejected by WebAuthn APIs on mobile phones.

### The Split-Horizon DNS Solution
1. **Public DNS Record**:
   - In your domain DNS manager (e.g., Cloudflare, Route53, Namecheap), manage the record:
     - **Host**: `staff-portal.artsci.com.ng`
     - **IP**: Once migrated, points to the office server IP (or managed locally via router DNS).
2. **Publicly-Trusted SSL Certificate**:
   - Caddy on Windows automatically obtains and renews a valid Let's Encrypt SSL certificate for `staff-portal.artsci.com.ng` using the **DNS-01 ACME challenge** (via Cloudflare/DNS API).
   - This requires **zero inbound port forwarding**!
3. **Staff Phone Experience**:
   - When staff connect to Office Wi-Fi and open `https://staff-portal.artsci.com.ng`:
   - DNS resolves to `<office-server-lan-ip>` (e.g. `192.168.0.100`).
   - The phone connects directly to the office Windows server over the local Wi-Fi.
   - The browser verifies the valid Let's Encrypt certificate with zero warnings.
   - The PWA installs cleanly, service worker caches assets, and WebAuthn passkeys work natively.

---

## 5. WebAuthn Passkey Origin Note (Major Benefit of Keeping the Domain)

WebAuthn passkeys are cryptographically bound to the domain (`relyingPartyId`).
- Because the origin remains **`https://staff-portal.artsci.com.ng`**, the domain matches what staff phones already know!
- Staff **do not lose their registered passkeys**! Their existing biometric device passkeys and PWA installation continue working without requiring staff to re-enroll or reinstall.
- If any staff member changes devices, the admin can approve the new device via **Admin Console &rarr; Approved Credentials**.

---

## 6. Connectivity Verification Test Protocol

From a staff phone:
1. Connect to **Office Wi-Fi A**.
2. Open browser and visit: `https://staff-portal.artsci.com.ng`.
3. Verify that the login page loads instantly.
4. Log in and verify that the Network Verification indicator shows **Office Network Verified**.
5. Disconnect from Wi-Fi A and connect to **Office Wi-Fi B**.
6. Refresh the page and verify that attendance remains verified.
7. Disconnect Wi-Fi completely (cellular data only):
   - Verify that the page is unreachable, proving that attendance cannot be spoofed from outside the office!
