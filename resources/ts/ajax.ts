export async function getData(url = "", data = {}) {

    const token = document.querySelector('meta[name="csrf-token"]')!.getAttribute('content');
    if (token === null) {
        return;
    }

    const response = await fetch(url, {
      method: "GET",
      mode: "no-cors",
      cache: "no-cache",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        'X-CSRF-TOKEN': token
      },
      redirect: "follow",
      referrerPolicy: "no-referrer",
    });
    return response.json();
}


